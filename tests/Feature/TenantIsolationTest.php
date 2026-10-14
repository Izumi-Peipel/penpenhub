<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Support\CurrentContext;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** Risiko R2: kebocoran data antar-tenant. Tes ini wajib hijau di CI. */
class TenantIsolationTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Company $a;
    private Company $b;
    private Brand $brandA1;
    private Brand $brandA2;
    private Brand $brandB;
    private Outlet $outletA1;
    private Outlet $outletA2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenants();

        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $this->brandA1 = Brand::factory()->create(['company_id' => $this->a->id]);
        $this->brandA2 = Brand::factory()->create(['company_id' => $this->a->id]);
        $this->brandB = Brand::factory()->create(['company_id' => $this->b->id]);
        $this->outletA1 = Outlet::factory()->for($this->brandA1)->create();
        $this->outletA2 = Outlet::factory()->for($this->brandA2)->create();
    }

    public function test_outlet_gets_company_id_from_its_brand(): void
    {
        $this->assertSame($this->a->id, (int) $this->outletA1->company_id);
    }

    public function test_owner_only_sees_own_company_data(): void
    {
        $this->actingAs($this->makeMember($this->a, Roles::OWNER));

        $this->assertEqualsCanonicalizing([$this->brandA1->id, $this->brandA2->id], Brand::pluck('id')->all());
        $this->assertNull(Brand::find($this->brandB->id));
        $this->assertSame(1, Company::count());
        $this->assertSame(2, Outlet::count());
    }

    public function test_user_without_membership_sees_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame(0, Brand::count());
        $this->assertSame(0, Outlet::count());
        $this->assertSame(0, Company::count());
    }

    public function test_super_admin_sees_everything(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $this->assertSame(3, Brand::count());
        $this->assertSame(2, Company::count());
    }

    public function test_internal_user_is_limited_to_assigned_clients_and_active_company_narrows(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);
        $this->actingAs($pm);

        $this->assertSame(2, Brand::count());

        Membership::create([
            'user_id' => $pm->id, 'company_id' => $this->b->id,
            'role' => Roles::ACCOUNT_MANAGER, 'scope_type' => Roles::SCOPE_COMPANY, 'scope_id' => $this->b->id,
        ]);

        $this->assertSame(3, Brand::count());

        app(CurrentContext::class)->setActive($pm, $this->b->id);
        $this->assertSame([$this->brandB->id], Brand::pluck('id')->all());
    }

    public function test_cannot_activate_a_company_without_membership(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->actingAs($owner);

        $this->expectException(AuthorizationException::class);
        app(CurrentContext::class)->setActive($owner, $this->b->id);
    }

    public function test_policies_deny_cross_company_access(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $brandB = Brand::withoutGlobalScopes()->find($this->brandB->id);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $this->brandA1));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $brandB));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $brandB));
        $this->assertFalse(Gate::forUser($owner)->allows('create', [Brand::class, $this->b]));
    }

    public function test_cannot_write_into_another_company(): void
    {
        $this->actingAs($this->makeMember($this->a, Roles::OWNER));

        $this->expectException(AuthorizationException::class);
        Brand::create(['company_id' => $this->b->id, 'name' => 'Selundupan']);
    }

    public function test_brand_manager_sees_only_assigned_brand_through_visible_to(): void
    {
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA1);
        $this->actingAs($bm);

        $this->assertSame([$this->brandA1->id], Brand::visibleTo($bm)->pluck('id')->all());
        $this->assertSame([$this->outletA1->id], Outlet::visibleTo($bm)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($bm)->allows('view', $this->brandA1));
        $this->assertFalse(Gate::forUser($bm)->allows('view', $this->brandA2));
    }

    public function test_outlet_manager_sees_only_own_outlet_and_parent_brand(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $this->actingAs($om);

        $this->assertSame([$this->outletA1->id], Outlet::visibleTo($om)->pluck('id')->all());
        $this->assertSame([$this->brandA1->id], Brand::visibleTo($om)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($om)->allows('view', $this->outletA2));
    }

    public function test_only_company_wide_managers_can_change_structure(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA1);
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $dev = $this->makeMember($this->a, Roles::DEVELOPER);

        $this->assertTrue(Gate::forUser($owner)->allows('create', [Brand::class, $this->a]));
        $this->assertTrue(Gate::forUser($pm)->allows('create', [Brand::class, $this->a]));
        $this->assertFalse(Gate::forUser($bm)->allows('create', [Brand::class, $this->a]));
        $this->assertFalse(Gate::forUser($om)->allows('create', [Brand::class, $this->a]));
        $this->assertFalse(Gate::forUser($dev)->allows('create', [Outlet::class, $this->brandA1]));
    }
}
