<?php

namespace Tests\Feature;

use App\Actions\AcceptInvitation;
use App\Actions\InviteUser;
use App\Mail\InvitationMail;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class InvitationTest extends TestCase
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
        Mail::fake();

        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $this->brandA1 = Brand::factory()->create(['company_id' => $this->a->id]);
        $this->brandA2 = Brand::factory()->create(['company_id' => $this->a->id]);
        $this->brandB = Brand::factory()->create(['company_id' => $this->b->id]);
        $this->outletA1 = Outlet::factory()->for($this->brandA1)->create();
        $this->outletA2 = Outlet::factory()->for($this->brandA2)->create();
    }

    private function invite(User $by, Company $company, string $role, ?int $scopeId = null, string $email = 'Baru@Example.com'): Invitation
    {
        return app(InviteUser::class)->handle($by, $company, $email, $role, $scopeId);
    }

    private function capturedToken(): string
    {
        $token = null;
        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });

        return $token;
    }

    public function test_owner_invites_brand_manager_and_token_is_stored_hashed(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $invitation = $this->invite($owner, $this->a, Roles::BRAND_MANAGER, $this->brandA1->id);

        $this->assertSame('baru@example.com', $invitation->email);
        $this->assertSame(Roles::SCOPE_BRAND, $invitation->scope_type);
        Mail::assertQueued(InvitationMail::class, fn (InvitationMail $m) => $m->hasTo('baru@example.com')
            && strlen($m->token) === 64
            && $invitation->token_hash === hash('sha256', $m->token));
        $this->assertDatabaseHas('activity_logs', ['action' => 'invitation.sent', 'user_id' => $owner->id]);
    }

    public function test_accepting_creates_user_membership_and_is_single_use(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->invite($owner, $this->a, Roles::BRAND_MANAGER, $this->brandA1->id);
        $token = $this->capturedToken();
        $input = ['name' => 'Budi', 'password' => 'Passw0rd!xyz', 'password_confirmation' => 'Passw0rd!xyz'];

        $user = app(AcceptInvitation::class)->handle($token, $input);

        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertTrue(Hash::check('Passw0rd!xyz', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_internal);
        $this->assertDatabaseHas('memberships', [
            'user_id' => $user->id, 'company_id' => $this->a->id, 'role' => Roles::BRAND_MANAGER,
            'scope_type' => 'brand', 'scope_id' => $this->brandA1->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'invitation.accepted', 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        app(AcceptInvitation::class)->handle($token, $input);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->invite($owner, $this->a, Roles::OUTLET_MANAGER, $this->outletA1->id);
        $token = $this->capturedToken();
        Invitation::withoutGlobalScopes()->update(['expires_at' => now()->subMinute()]);

        $this->expectException(ValidationException::class);
        app(AcceptInvitation::class)->handle($token, ['name' => 'X', 'password' => 'Passw0rd!xyz', 'password_confirmation' => 'Passw0rd!xyz']);
    }

    public function test_existing_user_gets_additional_membership_without_new_account(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $existing = User::factory()->create(['email' => 'baru@example.com']);
        $this->invite($owner, $this->a, Roles::OUTLET_MANAGER, $this->outletA1->id);

        $user = app(AcceptInvitation::class)->handle($this->capturedToken());

        $this->assertTrue($user->is($existing));
        $this->assertFalse($user->wasRecentlyCreated);
        $this->assertDatabaseHas('memberships', ['user_id' => $existing->id, 'role' => Roles::OUTLET_MANAGER]);
    }

    public function test_internal_invitee_is_flagged_internal(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);
        $this->invite($pm, $this->a, Roles::DEVELOPER);

        $user = app(AcceptInvitation::class)->handle($this->capturedToken(), [
            'name' => 'Dev', 'password' => 'Passw0rd!xyz', 'password_confirmation' => 'Passw0rd!xyz',
        ]);

        $this->assertTrue($user->is_internal);
    }

    public function test_owner_cannot_invite_internal_roles(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $this->expectException(AuthorizationException::class);
        $this->invite($owner, $this->a, Roles::ACCOUNT_MANAGER);
    }

    public function test_super_admin_role_cannot_be_granted_by_invitation(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $this->expectException(ValidationException::class);
        $this->invite($owner, $this->a, Roles::SUPER_ADMIN);
    }

    public function test_scope_must_belong_to_the_company(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $this->expectException(ValidationException::class);
        $this->invite($owner, $this->a, Roles::BRAND_MANAGER, $this->brandB->id);
    }

    public function test_brand_manager_can_only_invite_outlet_staff_within_own_brand(): void
    {
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA1);

        $this->assertInstanceOf(Invitation::class, $this->invite($bm, $this->a, Roles::OUTLET_MANAGER, $this->outletA1->id));

        try {
            $this->invite($bm, $this->a, Roles::OUTLET_MANAGER, $this->outletA2->id, 'lain@example.com');
            $this->fail('Brand Manager tidak boleh mengundang ke outlet brand lain.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        $this->invite($bm, $this->a, Roles::BRAND_MANAGER, $this->brandA1->id, 'lain2@example.com');
    }

    public function test_account_manager_is_limited_to_assigned_companies(): void
    {
        $pm = $this->makeMember($this->a, Roles::ACCOUNT_MANAGER);

        $this->assertInstanceOf(Invitation::class, $this->invite($pm, $this->a, Roles::OWNER));

        $this->expectException(AuthorizationException::class);
        $this->invite($pm, $this->b, Roles::OWNER, null, 'x@example.com');
    }

    public function test_super_admin_can_invite_anywhere(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->assertInstanceOf(Invitation::class, $this->invite($admin, $this->b, Roles::OWNER));
    }

    public function test_reinviting_replaces_the_pending_invitation(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        $this->invite($owner, $this->a, Roles::OWNER);
        $this->invite($owner, $this->a, Roles::OWNER);

        $this->assertSame(1, Invitation::withoutGlobalScopes()->where('email', 'baru@example.com')->count());
    }

    public function test_invitation_pages(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $this->invite($owner, $this->a, Roles::OUTLET_MANAGER, $this->outletA1->id);
        $token = $this->capturedToken();

        $this->get(route('invitations.show', $token))->assertOk()->assertSee('baru@example.com');
        $this->get(route('invitations.show', 'token-ngawur'))->assertStatus(410);

        $this->post(route('invitations.accept', $token), [
            'name' => 'Sari', 'password' => 'Passw0rd!xyz', 'password_confirmation' => 'Passw0rd!xyz',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $this->get(route('invitations.show', $token))->assertStatus(410);
    }
}
