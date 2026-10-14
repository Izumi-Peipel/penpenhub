<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;
        $gate = Gate::forUser($user);

        if ($attachable instanceof Ticket) {
            return $gate->allows('view', $attachable);
        }

        if ($attachable instanceof TicketComment) {
            $ticket = $attachable->ticket;

            if ($ticket === null) {
                return false;
            }

            return $attachable->is_internal
                ? $gate->allows('internalNote', $ticket)
                : $gate->allows('view', $ticket);
        }

        return false;
    }
}
