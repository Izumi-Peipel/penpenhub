<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();
    }

    public function test_guest_is_sent_to_the_fortify_login_not_a_filament_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_client_users_cannot_enter_the_panel(): void
    {
        $this->actingAs($this->makeMember($this->a, Roles::OWNER))->get('/admin')->assertForbidden();
        $this->actingAs($this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1))->get('/admin')->assertForbidden();
    }

    public function test_internal_user_sees_only_assigned_clients(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);

        $this->actingAs($pm)->followingRedirects()->followingRedirects()->get('/admin')->assertOk();
        $this->actingAs($pm)->get('/admin/companies')->assertOk()
            ->assertSee($this->a->name)
            ->assertDontSee($this->b->name);
    }

    public function test_internal_user_cannot_open_a_client_they_are_not_assigned_to(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);

        $this->actingAs($pm)->get("/admin/companies/{$this->b->id}/edit")->assertNotFound();
    }

    public function test_user_management_is_super_admin_only(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);
        $admin = User::factory()->create(['is_internal' => true]);
        $admin->assignRole('super_admin');

        $this->actingAs($pm)->get('/admin/users')->assertForbidden();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/plans')->assertOk();
    }
}
