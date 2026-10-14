<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Policies\Concerns\ResolvesMemberships;
use App\Support\Roles;

class BrandPolicy
{
    use ResolvesMemberships;

    public function viewAny(User $user): bool
    {
        return Membership::query()->where('user_id', $user->getKey())->exists();
    }

    public function view(User $user, Brand $brand): bool
    {
        foreach ($this->membershipsIn($user, $brand->company_id) as $m) {
            if (! $m->hasPermission('structure.view')) {
                continue;
            }

            if ($m->scope_type === Roles::SCOPE_COMPANY) {
                return true;
            }

            if ($m->scope_type === Roles::SCOPE_BRAND && $m->scope_id === (int) $brand->id) {
                return true;
            }

            // Outlet Manager melihat brand induk outlet-nya.
            if (
                $m->scope_type === Roles::SCOPE_OUTLET
                && Outlet::withoutGlobalScopes()->whereKey($m->scope_id)->where('brand_id', $brand->id)->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    /** Dipanggil sebagai authorize('create', [Brand::class, $company]). */
    public function create(User $user, ?Company $company = null): bool
    {
        return $company === null
            ? $this->hasAnyCompanyWide($user, 'structure.manage')
            : $this->hasCompanyWide($user, $company->id, 'structure.manage');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->hasCompanyWide($user, $brand->company_id, 'structure.manage');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand);
    }
}
