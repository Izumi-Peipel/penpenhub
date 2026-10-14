<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Policies\Concerns\ResolvesMemberships;
use App\Support\Roles;

class CompanyPolicy
{
    use ResolvesMemberships;

    public function view(User $user, Company $company): bool
    {
        return $this->membershipsIn($user, $company->id)
            ->contains(fn (Membership $m) => $m->hasPermission('structure.view'));
    }

    /** Panel admin: pengguna internal melihat klien yang ditugaskan (dibatasi CompanyScope). */
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_internal;
    }

    public function update(User $user, Company $company): bool
    {
        return $this->hasCompanyWide($user, $company->id, 'structure.manage');
    }

    public function delete(User $user, Company $company): bool
    {
        return false; // hanya Super Admin (Gate::before)
    }

    /** Membuat perusahaan hanya untuk Super Admin (ditangani Gate::before). */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Siapa boleh mengundang siapa (PRD bagian 6):
     *  - Owner (G): role klien mana pun di perusahaannya.
     *  - Brand Manager: hanya Outlet Manager untuk outlet di brand-nya.
     *  - Account Manager/PM (A): role mana pun di klien yang ditugaskan.
     */
    public function inviteUser(User $user, Company $company, string $role, string $scopeType, ?int $scopeId): bool
    {
        if (! in_array($role, Roles::MEMBERSHIP, true)) {
            return false;
        }

        foreach ($this->membershipsIn($user, $company->id) as $m) {
            if (! $m->hasPermission('user.invite')) {
                continue;
            }

            if ($m->role === Roles::ACCOUNT_MANAGER && $m->scope_type === Roles::SCOPE_COMPANY) {
                return true;
            }

            if ($m->role === Roles::OWNER && $m->scope_type === Roles::SCOPE_COMPANY && in_array($role, Roles::CLIENT, true)) {
                return true;
            }

            if (
                $m->role === Roles::BRAND_MANAGER && $m->scope_type === Roles::SCOPE_BRAND
                && $role === Roles::OUTLET_MANAGER && $scopeType === Roles::SCOPE_OUTLET
                && Outlet::withoutGlobalScopes()
                    ->whereKey($scopeId)
                    ->where('brand_id', $m->scope_id)
                    ->where('company_id', $company->id)
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }
}
