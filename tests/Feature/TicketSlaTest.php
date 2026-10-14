<?php

namespace Tests\Feature;

use App\Actions\AddComment;
use App\Actions\ChangeTicketPriority;
use App\Actions\TransitionTicket;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Support\Roles;
use App\Support\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class TicketSlaTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();
    }

    public function test_ticket_creation_populates_sla_due_dates(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        // Senin 12:00 WIB (jam operasional outletA1: 10:00 - 22:00)
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));

        // P1 ticket: 15 menit response, 120 menit resolution
        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1);

        $this->assertSame('P1', $ticket->priority);
        $this->assertNotNull($ticket->sla_response_due_at);
        $this->assertNotNull($ticket->sla_resolution_due_at);
        $this->assertSame('2026-10-05 12:15:00', $ticket->sla_response_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 14:00:00', $ticket->sla_resolution_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_responding_on_time_records_no_breach(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $dev = $this->makeMember($this->a, Roles::DEVELOPER);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1);

        // Respon 5 menit kemudian (masih sebelum batas 15 menit)
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:05:00', 'Asia/Jakarta'));
        app(TransitionTicket::class)->handle($dev, $ticket, TicketStatus::DITERIMA);

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->responded_at);
        $this->assertFalse($fresh->isResponseBreached());
        $this->assertFalse($fresh->sla_response_breached);
    }

    public function test_late_response_marks_response_breached(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $dev = $this->makeMember($this->a, Roles::DEVELOPER);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1);

        // Respon terlambat 30 menit kemudian (melewati batas 15 menit)
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:30:00', 'Asia/Jakarta'));
        app(TransitionTicket::class)->handle($dev, $ticket, TicketStatus::DITERIMA);

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->isResponseBreached());
        $this->assertTrue($fresh->sla_response_breached);
    }

    public function test_sla_resolution_pauses_on_waiting_for_client_and_extends_deadline_on_reply(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $dev = $this->makeMember($this->a, Roles::DEVELOPER);

        // Tiket P2 dibuat jam 12:00 WIB. Resolusi P2 = 240 menit (4 jam operasional) -> due at 16:00 WIB.
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1, ['symptom' => 'receipt_printer']); // P2

        $this->assertSame('P2', $ticket->priority);
        $this->assertSame('2026-10-05 16:00:00', $ticket->sla_resolution_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

        // Engineer terima dan mulai kerjakan
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:10:00', 'Asia/Jakarta'));
        app(TransitionTicket::class)->handle($dev, $ticket, TicketStatus::DITERIMA);
        app(TransitionTicket::class)->handle($dev, $ticket, TicketStatus::DIKERJAKAN);

        // Jam 13:00 (1 jam berjalan), engineer minta info klien -> status "menunggu_klien" (SLA jeda)
        Carbon::setTestNow(Carbon::parse('2026-10-05 13:00:00', 'Asia/Jakarta'));
        app(TransitionTicket::class)->handle($dev, $ticket, TicketStatus::MENUNGGU_KLIEN);

        $pausedTicket = $ticket->fresh();
        $this->assertTrue($pausedTicket->isSlaPaused());
        $this->assertNotNull($pausedTicket->sla_paused_at);

        // Klien membalas 2 jam kemudian jam 15:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 15:00:00', 'Asia/Jakarta'));
        app(AddComment::class)->handle($om, $ticket->fresh(), 'Ini foto struknya.');

        $resumedTicket = $ticket->fresh();
        $this->assertSame(TicketStatus::DIKERJAKAN, $resumedTicket->status);
        $this->assertFalse($resumedTicket->isSlaPaused());
        $this->assertNull($resumedTicket->sla_paused_at);
        $this->assertGreaterThan(0, $resumedTicket->total_paused_seconds);

        // Target resolusi diperpanjang 2 jam operasional dari 16:00 menjadi 18:00
        $this->assertSame('2026-10-05 18:00:00', $resumedTicket->sla_resolution_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_priority_change_recalculates_sla_deadlines(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $dev = $this->makeMember($this->a, Roles::DEVELOPER);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1); // P1 (15m respon, 120m resolusi)

        $this->assertSame('P1', $ticket->priority);
        $this->assertSame('2026-10-05 12:15:00', $ticket->sla_response_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 14:00:00', $ticket->sla_resolution_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

        // Ubah prioritas ke P2 (30m respon, 240m resolusi)
        app(ChangeTicketPriority::class)->handle($dev, $ticket, 'P2', 'Ternyata kasir cadangan masih ada');

        $fresh = $ticket->fresh();
        $this->assertSame('P2', $fresh->priority);
        $this->assertSame('2026-10-05 12:30:00', $fresh->sla_response_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 16:00:00', $fresh->sla_resolution_due_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_artisan_sla_check_command_detects_breaches_and_audits(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1); // P1: response due 12:15

        // Waktu maju ke 12:20 (lewat batas response 12:15)
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:20:00', 'Asia/Jakarta'));

        $this->artisan('penpenhub:check-sla')
            ->expectsOutputToContain('SLA Response breached')
            ->assertSuccessful();

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->sla_response_breached);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ticket.sla_response_breached',
            'company_id' => $this->a->id,
        ]);
    }
}
