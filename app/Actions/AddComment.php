<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class AddComment
{
    /** @param  \Illuminate\Http\UploadedFile[]  $files */
    public function handle(User $actor, Ticket $ticket, string $body, bool $internal = false, array $files = []): TicketComment
    {
        Gate::forUser($actor)->authorize($internal ? 'internalNote' : 'comment', $ticket);

        Validator::make(['body' => $body, 'attachments' => $files], [
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:'.config('penpenhub.attachments.max_files')],
            'attachments.*' => AttachmentStore::fileRules(),
        ])->validate();

        $comment = DB::transaction(function () use ($actor, $ticket, $body, $internal, $files) {
            $comment = TicketComment::create([
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'user_id' => $actor->getKey(),
                'body' => $body,
                'is_internal' => $internal,
            ]);

            if ($files !== []) {
                app(AttachmentStore::class)->store($files, $comment, $actor, (int) $ticket->company_id);
            }

            return $comment;
        });

        // Balasan klien pada "Menunggu klien" mengembalikan tiket ke "Dikerjakan" (jeda SLA berakhir).
        if (
            ! $internal
            && $ticket->status === TicketStatus::MENUNGGU_KLIEN
            && Gate::forUser($actor)->denies('manage', $ticket)
        ) {
            app(TransitionTicket::class)->apply($ticket, TicketStatus::DIKERJAKAN, $actor, 'klien membalas');
        }

        AuditLogger::log('ticket.commented', $ticket, ['internal' => $internal], $ticket->company_id, $actor);

        return $comment;
    }
}
