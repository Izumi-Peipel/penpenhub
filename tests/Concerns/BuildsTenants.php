<?php

namespace Tests\Concerns;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;

trait BuildsTenants
{
    protected function setUpTenants(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Membership::flushPermissionCache();
    }

    protected function makeMember(Company $company, string $role, Brand|Outlet|null $scope = null): User
    {
        $user = User::factory()->create(['is_internal' => Roles::isInternal($role)]);

        Membership::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role' => $role,
            'scope_type' => Roles::scopeTypeFor($role),
            'scope_id' => $scope?->id ?? $company->id,
        ]);

        return $user;
    }
}
