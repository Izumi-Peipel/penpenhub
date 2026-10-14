<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ChangeTicketPriority
{
    public function handle(User $actor, Ticket $ticket, string $priority, ?string $reason = null): Ticket
    {
        Gate::forUser($actor)->authorize('manage', $ticket);

        Validator::make(['priority' => $priority], ['priority' => ['required', Rule::in(Ticket::PRIORITIES)]])->validate();

        $from = $ticket->priority;

        if ($from !== $priority) {
            $updates = ['priority' => $priority];

            $outlet = $ticket->outlet;
            $baseTime = $ticket->created_at ?: now();

            if ($ticket->responded_at === null) {
                $updates['sla_response_due_at'] = \App\Support\SlaCalculator::calculateResponseDue($priority, $baseTime, $outlet);
            }

            if ($ticket->resolved_at === null) {
                $newResolutionDue = \App\Support\SlaCalculator::calculateResolutionDue($priority, $baseTime, $outlet);
                if ($ticket->total_paused_seconds > 0) {
                    $newResolutionDue = $newResolutionDue->addSeconds($ticket->total_paused_seconds);
                }
                $updates['sla_resolution_due_at'] = $newResolutionDue;
            }

            $ticket->forceFill($updates)->save();

            AuditLogger::log('ticket.priority_changed', $ticket, [
                'from' => $from, 'to' => $priority, 'reason' => $reason,
            ], $ticket->company_id, $actor);
        }


        return $ticket;
    }
}
