<?php

namespace App\Policies\Concerns;

use App\Models\Membership;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Collection;

trait ResolvesMemberships
{
    /** @return Collection<int, Membership> */
    protected function membershipsIn(User $user, int $companyId): Collection
    {
        return Membership::query()
            ->where('user_id', $user->getKey())
            ->where('company_id', $companyId)
            ->get();
    }

    /** Izin yang berlaku untuk seluruh perusahaan (scope G / klien yang ditugaskan). */
    protected function hasCompanyWide(User $user, int $companyId, string $permission): bool
    {
        return $this->membershipsIn($user, $companyId)->contains(
            fn (Membership $m) => $m->scope_type === Roles::SCOPE_COMPANY && $m->hasPermission($permission)
        );
    }

    /** Punya izin company-wide di perusahaan mana pun (untuk pengecekan tingkat kelas, mis. Filament). */
    protected function hasAnyCompanyWide(User $user, string $permission): bool
    {
        return Membership::query()->where('user_id', $user->getKey())->get()->contains(
            fn (Membership $m) => $m->scope_type === Roles::SCOPE_COMPANY && $m->hasPermission($permission)
        );
    }
}
