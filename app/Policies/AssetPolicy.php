<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Policies\Concerns\ResolvesMemberships;
use App\Support\Roles;

class AssetPolicy
{
    use ResolvesMemberships;

    public function viewAny(User $user): bool
    {
        return Membership::query()->where('user_id', $user->getKey())->exists();
    }

    public function view(User $user, Asset $asset): bool
    {
        foreach ($this->membershipsIn($user, $asset->company_id) as $m) {
            if (! $m->hasPermission('structure.view')) {
                continue;
            }

            if ($m->scope_type === Roles::SCOPE_COMPANY) {
                return true;
            }

            if ($m->scope_type === Roles::SCOPE_BRAND && $m->scope_id === (int) $asset->brand_id) {
                return true;
            }

            if ($m->scope_type === Roles::SCOPE_OUTLET) {
                if ($asset->outlet_id !== null && $m->scope_id === (int) $asset->outlet_id) {
                    return true;
                }

                if (
                    $asset->outlet_id === null
                    && Outlet::withoutGlobalScopes()->whereKey($m->scope_id)->where('brand_id', $asset->brand_id)->exists()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** authorize('create', [Asset::class, $brand]); tanpa brand = cek tingkat kelas (Filament). */
    public function create(User $user, ?Brand $brand = null): bool
    {
        return $brand === null
            ? $this->hasAnyCompanyWide($user, 'asset.manage')
            : $this->hasCompanyWide($user, $brand->company_id, 'asset.manage');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->hasCompanyWide($user, $asset->company_id, 'asset.manage');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
