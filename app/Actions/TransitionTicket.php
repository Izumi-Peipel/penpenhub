<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TicketStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class TransitionTicket
{
    public function handle(User $actor, Ticket $ticket, string $to, ?string $reason = null): Ticket
    {
        if (! in_array($to, TicketStatus::TRANSITIONS[$ticket->status] ?? [], true)) {
            throw ValidationException::withMessages(['to' => 'Perubahan status tidak valid dari '.TicketStatus::label($ticket->status).'.']);
        }

        if (! in_array($to, TicketStatus::availableFor($ticket, $actor), true)) {
            throw new AuthorizationException('Anda tidak berhak mengubah status ini.');
        }

        $isClientReject = $ticket->status === TicketStatus::TERSELESAIKAN && $to === TicketStatus::DIKERJAKAN;

        if ($isClientReject && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan solusi wajib diisi.']);
        }

        $this->apply($ticket, $to, $actor, $reason);

        if ($isClientReject) {
            TicketComment::create([
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'user_id' => $actor->getKey(),
                'body' => 'Solusi ditolak: '.trim((string) $reason),
                'is_internal' => false,
            ]);
        }

        return $ticket;
    }

    /** Terapkan perubahan tanpa cek otorisasi; dipakai juga untuk perubahan otomatis (mis. klien membalas). */
    public function apply(Ticket $ticket, string $to, User $actor, ?string $reason = null): Ticket
    {
        $now = now();
        $from = $ticket->status;
        $updates = ['status' => $to];

        if ($to === TicketStatus::DITERIMA && $ticket->responded_at === null) {
            $updates['responded_at'] = $now;
            if ($ticket->sla_response_due_at && $now->isAfter($ticket->sla_response_due_at)) {
                $updates['sla_response_breached'] = true;
            }
        }

        // Mulai jeda SLA jika masuk ke "menunggu_klien"
        if ($to === TicketStatus::MENUNGGU_KLIEN && $ticket->sla_paused_at === null) {
            $updates['sla_paused_at'] = $now;
        }

        // Akhiri jeda SLA jika keluar dari "menunggu_klien"
        if ($from === TicketStatus::MENUNGGU_KLIEN && $to !== TicketStatus::MENUNGGU_KLIEN && $ticket->sla_paused_at !== null) {
            $pausedSeconds = abs((int) $ticket->sla_paused_at->diffInSeconds($now));
            $updates['total_paused_seconds'] = (int) $ticket->total_paused_seconds + $pausedSeconds;
            $updates['sla_paused_at'] = null;


            $newResolutionDue = \App\Support\SlaCalculator::extendResolutionDueForPause($ticket, $ticket->sla_paused_at, $now);
            if ($newResolutionDue !== null) {
                $updates['sla_resolution_due_at'] = $newResolutionDue;
            }
        }

        if ($to === TicketStatus::TERSELESAIKAN) {
            $updates['resolved_at'] = $now;
            if ($ticket->sla_resolution_due_at && $now->isAfter($ticket->sla_resolution_due_at)) {
                $updates['sla_resolution_breached'] = true;
            }
        }

        if ($from === TicketStatus::TERSELESAIKAN && $to === TicketStatus::DIKERJAKAN) {
            $updates['resolved_at'] = null;
        }

        if ($to === TicketStatus::DITUTUP) {
            $updates['closed_at'] = $now;
        }

        $ticket->forceFill($updates)->save();

        AuditLogger::log('ticket.status_changed', $ticket, [
            'from' => $from, 'to' => $to, 'reason' => $reason,
        ], $ticket->company_id, $actor);

        return $ticket;
    }

}
