<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/** Paket: internal boleh melihat; mengubah hanya Super Admin (Gate::before). */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_internal;
    }

    public function view(User $user, Plan $plan): bool
    {
        return (bool) $user->is_internal;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Plan $plan): bool
    {
        return false;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return false;
    }
}
