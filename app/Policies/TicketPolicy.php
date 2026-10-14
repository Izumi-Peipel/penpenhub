<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\Concerns\ResolvesMemberships;
use App\Support\Roles;

class TicketPolicy
{
    use ResolvesMemberships;

    public function viewAny(User $user): bool
    {
        return Membership::query()->where('user_id', $user->getKey())->exists();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->membershipsIn($user, $ticket->company_id)->contains(
            fn (Membership $m) => $m->hasPermission('ticket.view') && $this->covers($m, $ticket)
        );
    }

    /** authorize('create', [Ticket::class, $brand, $outlet]) */
    public function create(User $user, Brand $brand, ?Outlet $outlet = null): bool
    {
        return $this->membershipsIn($user, $brand->company_id)->contains(
            fn (Membership $m) => $m->hasPermission('ticket.create') && match ($m->scope_type) {
                Roles::SCOPE_COMPANY => true,
                Roles::SCOPE_BRAND => $m->scope_id === (int) $brand->id,
                Roles::SCOPE_OUTLET => $outlet !== null && $m->scope_id === (int) $outlet->id,
                default => false,
            }
        );
    }

    /** Engineer/PM: ubah status, prioritas, penugasan. */
    public function manage(User $user, Ticket $ticket): bool
    {
        return $this->hasCompanyWide($user, $ticket->company_id, 'ticket.manage');
    }

    /** Komentar publik: siapa pun yang boleh melihat tiket. */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** Catatan internal: hanya pengguna internal ber-izin (tidak pernah klien). */
    public function internalNote(User $user, Ticket $ticket): bool
    {
        return $this->hasCompanyWide($user, $ticket->company_id, 'ticket.internal_note');
    }

    /** Konfirmasi tutup / tolak solusi. Outlet Manager hanya untuk tiket miliknya. */
    public function confirmClose(User $user, Ticket $ticket): bool
    {
        return $this->membershipsIn($user, $ticket->company_id)->contains(
            fn (Membership $m) => $m->hasPermission('ticket.confirm_close') && match ($m->scope_type) {
                Roles::SCOPE_COMPANY => true,
                Roles::SCOPE_BRAND => $m->scope_id === (int) $ticket->brand_id,
                Roles::SCOPE_OUTLET => (int) $ticket->reported_by === (int) $user->getKey(),
                default => false,
            }
        );
    }

    private function covers(Membership $m, Ticket $ticket): bool
    {
        return match ($m->scope_type) {
            Roles::SCOPE_COMPANY => true,
            Roles::SCOPE_BRAND => $m->scope_id === (int) $ticket->brand_id,
            Roles::SCOPE_OUTLET => $ticket->outlet_id !== null && $m->scope_id === (int) $ticket->outlet_id,
            default => false,
        };
    }
}
