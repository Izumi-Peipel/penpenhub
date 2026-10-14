<?php

namespace App\Actions;

use App\Mail\InvitationMail;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Outlet;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class InviteUser
{
    public function handle(User $inviter, Company $company, string $email, string $role, ?int $scopeId = null): Invitation
    {
        Validator::make(['email' => $email, 'role' => $role], [
            'email' => ['required', 'email:rfc', 'max:190'],
            'role' => ['required', Rule::in(Roles::MEMBERSHIP)],
        ])->validate();

        $scopeType = Roles::scopeTypeFor($role);
        $scopeId = $this->resolveScopeId($company, $scopeType, $scopeId);

        Gate::forUser($inviter)->authorize('inviteUser', [$company, $role, $scopeType, $scopeId]);

        $email = Str::lower(trim($email));
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($company, $email, $role, $scopeType, $scopeId, $inviter, $token) {
            // Undangan baru menggantikan undangan lama yang belum diterima untuk kombinasi yang sama.
            Invitation::query()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->where('role', $role)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->whereNull('accepted_at')
                ->delete();

            return Invitation::create([
                'company_id' => $company->id,
                'email' => $email,
                'role' => $role,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $inviter->getKey(),
                'expires_at' => now()->addDays((int) config('penpenhub.invitation_ttl_days', 7)),
            ]);
        });

        Mail::to($email)->queue(new InvitationMail($invitation, $token));

        AuditLogger::log('invitation.sent', $invitation, ['email' => $email, 'role' => $role], $company->id, $inviter);

        return $invitation;
    }

    private function resolveScopeId(Company $company, string $scopeType, ?int $scopeId): int
    {
        if ($scopeType === Roles::SCOPE_COMPANY) {
            return $company->id;
        }

        $exists = match ($scopeType) {
            Roles::SCOPE_BRAND => Brand::withoutGlobalScopes()->whereKey($scopeId)->where('company_id', $company->id)->exists(),
            Roles::SCOPE_OUTLET => Outlet::withoutGlobalScopes()->whereKey($scopeId)->where('company_id', $company->id)->exists(),
            default => false,
        };

        if (! $exists) {
            throw ValidationException::withMessages(['scope_id' => 'Brand/outlet tidak ditemukan di perusahaan ini.']);
        }

        return (int) $scopeId;
    }
}
