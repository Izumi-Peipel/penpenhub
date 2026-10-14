<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Policies\Concerns\ResolvesMemberships;
use App\Support\Roles;

class OutletPolicy
{
    use ResolvesMemberships;

    public function viewAny(User $user): bool
    {
        return Membership::query()->where('user_id', $user->getKey())->exists();
    }

    public function view(User $user, Outlet $outlet): bool
    {
        foreach ($this->membershipsIn($user, $outlet->company_id) as $m) {
            if (! $m->hasPermission('structure.view')) {
                continue;
            }

            if (
                $m->scope_type === Roles::SCOPE_COMPANY
                || ($m->scope_type === Roles::SCOPE_BRAND && $m->scope_id === (int) $outlet->brand_id)
                || ($m->scope_type === Roles::SCOPE_OUTLET && $m->scope_id === (int) $outlet->id)
            ) {
                return true;
            }
        }

        return false;
    }

    /** Dipanggil sebagai authorize('create', [Outlet::class, $brand]). */
    public function create(User $user, ?Brand $brand = null): bool
    {
        return $brand === null
            ? $this->hasAnyCompanyWide($user, 'structure.manage')
            : $this->hasCompanyWide($user, $brand->company_id, 'structure.manage');
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return $this->hasCompanyWide($user, $outlet->company_id, 'structure.manage');
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return $this->update($user, $outlet);
    }
}
