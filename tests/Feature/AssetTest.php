<?php

namespace Tests\Feature;

use App\Actions\ManageAsset;
use App\Models\Asset;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();
    }

    private function input(array $over = []): array
    {
        return array_merge(['type' => 'website', 'name' => 'Web Baru', 'endpoint_url' => 'https://baru.example.test'], $over);
    }

    public function test_owner_account_manager_and_developer_can_create_assets(): void
    {
        foreach ([Roles::OWNER, Roles::ACCOUNT_MANAGER, Roles::DEVELOPER] as $role) {
            $user = $this->makeMember($this->a, $role);
            $asset = app(ManageAsset::class)->create($user, $this->brandA1, $this->input(['name' => "Aset {$role}"]));

            $this->assertSame((int) $this->a->id, (int) $asset->company_id);
            $this->assertDatabaseHas('activity_logs', ['action' => 'asset.created', 'user_id' => $user->id]);
        }
    }

    public function test_brand_manager_outlet_manager_and_finance_cannot_create_assets(): void
    {
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA1);
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $fin = $this->makeMember($this->a, Roles::FINANCE);

        foreach ([$bm, $om, $fin] as $user) {
            try {
                app(ManageAsset::class)->create($user, $this->brandA1, $this->input());
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_only_http_and_https_urls_are_accepted(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        foreach (['javascript:alert(1)', 'ftp://files.example.test', 'bukan url'] as $bad) {
            try {
                app(ManageAsset::class)->create($owner, $this->brandA1, $this->input(['endpoint_url' => $bad]));
                $this->fail("URL {$bad} seharusnya ditolak.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_outlet_must_belong_to_the_brand(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $this->expectException(ValidationException::class);
        app(ManageAsset::class)->create($owner, $this->brandA1, $this->input(['outlet_id' => $this->outletA2->id]));
    }

    public function test_asset_in_use_by_a_ticket_cannot_be_deleted(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->openTicket($owner, $this->outletA1, $this->posA1);

        $this->expectException(ValidationException::class);
        app(ManageAsset::class)->delete($owner, $this->posA1);
    }

    public function test_unused_asset_can_be_deleted(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        app(ManageAsset::class)->delete($owner, $this->webA1);

        $this->assertDatabaseMissing('assets', ['id' => $this->webA1->id]);
    }

    public function test_visibility_follows_membership_scope(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA2);

        $this->actingAs($owner);
        $this->assertEqualsCanonicalizing(
            [$this->posA1->id, $this->webA1->id, $this->posA2->id],
            Asset::pluck('id')->all()
        );
        $this->assertNull(Asset::find($this->posB->id));

        // Outlet Manager: aset outlet-nya + aset level brand induk, bukan aset outlet lain.
        $this->assertEqualsCanonicalizing(
            [$this->posA1->id, $this->webA1->id],
            Asset::visibleTo($om)->pluck('id')->all()
        );
        $this->assertSame([$this->posA2->id], Asset::visibleTo($bm)->pluck('id')->all());
    }
}
