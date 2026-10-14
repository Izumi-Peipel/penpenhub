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
            $ticket->forceFill(['priority' => $priority])->save();

            AuditLogger::log('ticket.priority_changed', $ticket, [
                'from' => $from, 'to' => $priority, 'reason' => $reason,
            ], $ticket->company_id, $actor);
        }

        return $ticket;
    }
}
