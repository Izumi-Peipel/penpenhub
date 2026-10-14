<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Support\AuditLogger;
use App\Support\TicketStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTicketSla extends Command
{
    protected $signature = 'penpenhub:check-sla';

    protected $description = 'Periksa tenggat SLA tiket terbuka dan catat pelanggaran (breach)';

    public function handle(): int
    {
        $now = now();

        // 1. Cek pelanggaran SLA respon untuk tiket yang belum direspon
        $unrespondedTickets = Ticket::withoutGlobalScopes()
            ->whereNull('responded_at')
            ->where('status', '!=', TicketStatus::DITUTUP)
            ->whereNotNull('sla_response_due_at')
            ->where('sla_response_due_at', '<', $now)
            ->where('sla_response_breached', false)
            ->get();

        foreach ($unrespondedTickets as $ticket) {
            $ticket->forceFill(['sla_response_breached' => true])->saveQuietly();

            AuditLogger::log('ticket.sla_response_breached', $ticket, [
                'priority' => $ticket->priority,
                'due_at' => $ticket->sla_response_due_at?->toISOString(),
                'detected_at' => $now->toISOString(),
            ], $ticket->company_id);

            Log::warning("SLA Response breached for ticket #{$ticket->ticket_number} (priority: {$ticket->priority})");
            $this->warn("SLA Response breached: {$ticket->ticket_number}");
        }

        // 2. Cek pelanggaran SLA resolusi untuk tiket yang belum selesai dan tidak sedang dijeda
        $unresolvedTickets = Ticket::withoutGlobalScopes()
            ->whereNull('resolved_at')
            ->where('status', '!=', TicketStatus::DITUTUP)
            ->where('status', '!=', TicketStatus::MENUNGGU_KLIEN)
            ->whereNull('sla_paused_at')
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', $now)
            ->where('sla_resolution_breached', false)
            ->get();

        foreach ($unresolvedTickets as $ticket) {
            $ticket->forceFill(['sla_resolution_breached' => true])->saveQuietly();

            AuditLogger::log('ticket.sla_resolution_breached', $ticket, [
                'priority' => $ticket->priority,
                'due_at' => $ticket->sla_resolution_due_at?->toISOString(),
                'detected_at' => $now->toISOString(),
            ], $ticket->company_id);

            Log::warning("SLA Resolution breached for ticket #{$ticket->ticket_number} (priority: {$ticket->priority})");
            $this->warn("SLA Resolution breached: {$ticket->ticket_number}");
        }

        $this->info("Pemeriksaan SLA selesai. {$unrespondedTickets->count()} response breach, {$unresolvedTickets->count()} resolution breach.");

        return self::SUCCESS;
    }
}
