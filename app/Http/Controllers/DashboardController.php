<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Support\ContextSwitcher;
use App\Support\QuotaManager;
use App\Support\TicketStatus;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuotaManager $quota, ContextSwitcher $switcher)
    {
        $user  = $request->user()->load('memberships.company');
        $state = $switcher->state($user);

        // Perusahaan konteks aktif (null = belum dipilih / internal)
        $companyId = $state['company_id'];

        // Ringkasan kuota hanya tersedia jika ada company_id terpilih
        $quotaSummary = $companyId ? $quota->summary($companyId) : null;

        // Tiket aktif (bukan ditutup) yang dapat dilihat user
        $activeTickets = Ticket::visibleTo($user)
            ->with(['outlet', 'brand'])
            ->where('tickets.status', '!=', TicketStatus::DITUTUP)
            ->orderBy('tickets.priority')
            ->orderByDesc('tickets.id')
            ->limit(5)
            ->get();

        // Statistik ringkas
        $stats = [
            'open'     => Ticket::visibleTo($user)->where('tickets.status', '!=', TicketStatus::DITUTUP)->count(),
            'breached' => Ticket::visibleTo($user)
                ->where('tickets.status', '!=', TicketStatus::DITUTUP)
                ->where('sla_resolution_breached', true)
                ->count(),
        ];

        return view('dashboard', compact('user', 'quotaSummary', 'activeTickets', 'stats'));
    }
}
