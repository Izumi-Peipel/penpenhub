<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Membership;
use App\Support\ContextSwitcher;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ContextSwitcherTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();
    }

    public function test_single_outlet_user_gets_full_defaults(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        $state = app(ContextSwitcher::class)->state($om);

        $this->assertSame((int) $this->a->id, $state['company_id']);
        $this->assertSame((int) $this->brandA1->id, $state['brand_id']);
        $this->assertSame((int) $this->outletA1->id, $state['outlet_id']);
    }

    public function test_multi_scope_owner_has_no_default_below_company(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $state = app(ContextSwitcher::class)->state($owner);

        $this->assertSame((int) $this->a->id, $state['company_id']);
        $this->assertNull($state['brand_id']);
        $this->assertNull($state['outlet_id']);
        $this->assertCount(2, $state['brands']);
    }

    public function test_selecting_an_outlet_derives_brand_and_company(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->actingAs($owner);

        app(ContextSwitcher::class)->select($owner, null, null, $this->outletA2->id);
        $state = app(ContextSwitcher::class)->state($owner);

        $this->assertSame((int) $this->brandA2->id, $state['brand_id']);
        $this->assertSame((int) $this->outletA2->id, $state['outlet_id']);
    }

    public function test_selecting_a_brand_clears_the_outlet(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->actingAs($owner);
        $switcher = app(ContextSwitcher::class);

        $switcher->select($owner, null, null, $this->outletA1->id);
        $switcher->select($owner, null, $this->brandA2->id, null);

        $state = $switcher->state($owner);
        $this->assertSame((int) $this->brandA2->id, $state['brand_id']);
        // brandA2 hanya punya satu outlet, jadi default otomatis; yang penting bukan outlet lama.
        $this->assertNotSame((int) $this->outletA1->id, $state['outlet_id']);
    }

    public function test_cannot_select_something_outside_own_scope(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $this->actingAs($om);

        $this->expectException(ValidationException::class);
        app(ContextSwitcher::class)->select($om, null, null, $this->outletA2->id);
    }

    public function test_cannot_select_another_companys_brand(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->actingAs($owner);

        $this->expectException(ValidationException::class);
        app(ContextSwitcher::class)->select($owner, null, $this->brandB->id, null);
    }

    public function test_internal_user_switching_company_narrows_data_scope(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);
        Membership::create([
            'user_id' => $pm->id, 'company_id' => $this->b->id,
            'role' => Roles::ACCOUNT_MANAGER, 'scope_type' => Roles::SCOPE_COMPANY, 'scope_id' => $this->b->id,
        ]);
        $this->actingAs($pm);
        $switcher = app(ContextSwitcher::class);

        $this->assertCount(2, $switcher->companies($pm));
        $this->assertSame(3, Brand::count());

        $switcher->select($pm, $this->b->id, null, null);
        $this->assertSame([$this->brandB->id], Brand::pluck('id')->all());
        $this->assertCount(2, $switcher->companies($pm)); // daftar tetap lengkap agar bisa berpindah lagi
    }

    public function test_context_route_stores_selection_and_rejects_foreign_ids(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $this->actingAs($owner)->from('/dashboard')->post('/context', ['brand_id' => $this->brandA1->id])
            ->assertRedirect('/dashboard');
        $this->assertSame($this->brandA1->id, (int) session(ContextSwitcher::BRAND_KEY));

        $this->actingAs($owner)->from('/dashboard')->post('/context', ['brand_id' => $this->brandB->id])
            ->assertSessionHasErrors('brand_id');
    }
}
