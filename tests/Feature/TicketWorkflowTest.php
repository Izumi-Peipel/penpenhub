<?php

namespace Tests\Feature;

use App\Actions\AddComment;
use App\Actions\ChangeTicketPriority;
use App\Actions\TransitionTicket;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private User $om;
    private User $dev;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();

        $this->om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $this->dev = $this->makeMember($this->a, Roles::DEVELOPER);
        $this->ticket = $this->openTicket($this->om, $this->outletA1, $this->posA1);
    }

    private function move(User $by, string $to, ?string $reason = null): Ticket
    {
        return app(TransitionTicket::class)->handle($by, $this->ticket->fresh(), $to, $reason);
    }

    public function test_engineer_walks_the_happy_path_and_timestamps_are_set(): void
    {
        $t = $this->move($this->dev, 'diterima');
        $this->assertNotNull($t->responded_at);

        $this->move($this->dev, 'dikerjakan');
        $t = $this->move($this->dev, 'terselesaikan');
        $this->assertNotNull($t->resolved_at);

        $t = $this->move($this->om, 'ditutup'); // klien mengonfirmasi
        $this->assertNotNull($t->closed_at);
        $this->assertSame('ditutup', $t->status);
        $this->assertSame(4, \App\Models\ActivityLog::where('action', 'ticket.status_changed')->count());
    }

    public function test_invalid_transition_is_a_validation_error(): void
    {
        $this->expectException(ValidationException::class);
        $this->move($this->dev, 'terselesaikan'); // baru -> terselesaikan tidak ada di diagram
    }

    public function test_client_cannot_use_engineer_transitions(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->move($this->om, 'diterima');
    }

    public function test_client_cannot_close_before_resolution(): void
    {
        $this->move($this->dev, 'diterima');

        $this->expectException(ValidationException::class);
        $this->move($this->om, 'ditutup'); // diterima -> ditutup tidak valid
    }

    public function test_client_rejecting_a_solution_needs_a_reason_and_reopens(): void
    {
        $this->move($this->dev, 'diterima');
        $this->move($this->dev, 'dikerjakan');
        $this->move($this->dev, 'terselesaikan');

        try {
            $this->move($this->om, 'dikerjakan');
            $this->fail('Alasan wajib.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $t = $this->move($this->om, 'dikerjakan', 'Kasir masih error');

        $this->assertSame('dikerjakan', $t->status);
        $this->assertNull($t->resolved_at);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $t->id, 'is_internal' => false, 'body' => 'Solusi ditolak: Kasir masih error']);
    }

    public function test_only_the_reporter_outlet_manager_can_confirm_close(): void
    {
        $other = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $this->move($this->dev, 'diterima');
        $this->move($this->dev, 'dikerjakan');
        $this->move($this->dev, 'terselesaikan');

        $this->expectException(AuthorizationException::class);
        $this->move($other, 'ditutup'); // outlet sama tetapi bukan pelapor
    }

    public function test_client_reply_while_waiting_returns_ticket_to_in_progress(): void
    {
        $this->move($this->dev, 'diterima');
        $this->move($this->dev, 'dikerjakan');
        $this->move($this->dev, 'menunggu_klien');

        app(AddComment::class)->handle($this->om, $this->ticket->fresh(), 'Sudah saya restart, masih sama.');

        $this->assertSame('dikerjakan', $this->ticket->fresh()->status);
    }

    public function test_engineer_comment_does_not_change_waiting_status(): void
    {
        $this->move($this->dev, 'diterima');
        $this->move($this->dev, 'dikerjakan');
        $this->move($this->dev, 'menunggu_klien');

        app(AddComment::class)->handle($this->dev, $this->ticket->fresh(), 'Mohon kirim log terbaru.');

        $this->assertSame('menunggu_klien', $this->ticket->fresh()->status);
    }

    public function test_internal_notes_are_invisible_to_clients_and_cannot_be_written_by_them(): void
    {
        app(AddComment::class)->handle($this->dev, $this->ticket, 'Dugaan: lisensi POS kedaluwarsa', true);
        app(AddComment::class)->handle($this->dev, $this->ticket, 'Kami sedang cek.');

        $this->assertCount(1, $this->ticket->visibleComments($this->om));
        $this->assertCount(2, $this->ticket->visibleComments($this->dev));

        $this->expectException(AuthorizationException::class);
        app(AddComment::class)->handle($this->om, $this->ticket, 'catatan internal palsu', true);
    }

    public function test_user_without_access_cannot_comment(): void
    {
        $outsider = $this->makeMember($this->b, Roles::OWNER);

        $this->expectException(AuthorizationException::class);
        app(AddComment::class)->handle($outsider, $this->ticket, 'halo');
    }

    public function test_priority_change_is_restricted_validated_and_audited(): void
    {
        $t = app(ChangeTicketPriority::class)->handle($this->dev, $this->ticket, 'P2', 'Ada workaround');

        $this->assertSame('P2', $t->priority);
        $log = \App\Models\ActivityLog::where('action', 'ticket.priority_changed')->latest('id')->first();
        $this->assertSame(['from' => 'P1', 'to' => 'P2', 'reason' => 'Ada workaround'], $log->meta);

        try {
            app(ChangeTicketPriority::class)->handle($this->dev, $this->ticket, 'P9');
            $this->fail('P9 tidak valid.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        app(ChangeTicketPriority::class)->handle($this->om, $this->ticket, 'P4');
    }
}
