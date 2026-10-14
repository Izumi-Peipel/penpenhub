<?php

namespace App\Http\Controllers;

use App\Actions\AddComment;
use App\Actions\ChangeTicketPriority;
use App\Actions\TransitionTicket;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Support\ContextSwitcher;
use App\Support\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(Request $request, ContextSwitcher $switcher)
    {
        $user = $request->user();
        $state = $switcher->state($user);
        $filter = $request->query('status', 'open');

        $query = Ticket::visibleTo($user)->with(['brand', 'outlet', 'asset']);

        if ($state['outlet_id']) {
            $query->where('tickets.outlet_id', $state['outlet_id']);
        } elseif ($state['brand_id']) {
            $query->where('tickets.brand_id', $state['brand_id']);
        }

        if ($filter === 'closed') {
            $query->where('tickets.status', TicketStatus::DITUTUP);
        } elseif ($filter !== 'all') {
            $query->where('tickets.status', '!=', TicketStatus::DITUTUP);
        }

        return view('tickets.index', [
            'tickets' => $query->orderBy('tickets.priority')->orderByDesc('tickets.id')->paginate(20)->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function show(Request $request, Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        $user = $request->user();
        $comments = $ticket->visibleComments($user);

        $visibleAttachments = fn ($attachments) => $attachments
            ->filter(fn (Attachment $a) => Gate::forUser($user)->allows('view', $a));

        $history = ActivityLog::query()
            ->with('user')
            ->where('subject_type', $ticket->getMorphClass())
            ->where('subject_id', $ticket->id)
            ->whereIn('action', ['ticket.created', 'ticket.status_changed', 'ticket.priority_changed'])
            ->orderBy('id')
            ->get();

        return view('tickets.show', [
            'ticket' => $ticket->load(['brand', 'outlet', 'asset', 'reporter']),
            'comments' => $comments,
            'attachments' => $visibleAttachments($ticket->attachments()->get()),
            'commentAttachments' => $visibleAttachments(
                Attachment::query()->where('attachable_type', 'App\\Models\\TicketComment')
                    ->whereIn('attachable_id', $comments->pluck('id'))->get()
            )->groupBy('attachable_id'),
            'history' => $history,
            'transitions' => TicketStatus::availableFor($ticket, $user),
            'canManage' => Gate::forUser($user)->allows('manage', $ticket),
            'canInternal' => Gate::forUser($user)->allows('internalNote', $ticket),
        ]);
    }

    public function comment(Request $request, Ticket $ticket, AddComment $action)
    {
        $action->handle(
            $request->user(),
            $ticket,
            (string) $request->input('body'),
            $request->boolean('internal'),
            $request->file('attachments', []) ?: [],
        );

        return back()->with('status', 'Komentar terkirim.');
    }

    public function status(Request $request, Ticket $ticket, TransitionTicket $action)
    {
        $action->handle($request->user(), $ticket, (string) $request->input('to'), $request->input('reason'));

        return back()->with('status', 'Status diperbarui.');
    }

    public function priority(Request $request, Ticket $ticket, ChangeTicketPriority $action)
    {
        $action->handle($request->user(), $ticket, (string) $request->input('priority'), $request->input('reason'));

        return back()->with('status', 'Prioritas diperbarui.');
    }
}
