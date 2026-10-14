<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Support\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class TicketCreationTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();
    }

    public function test_us1_outlet_manager_reports_pos_down_during_opening_hours_as_p1(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1);

        $this->assertSame('P1', $ticket->priority);
        $this->assertSame('baru', $ticket->status);
        $this->assertSame((int) $this->a->id, (int) $ticket->company_id);
        $this->assertSame(sprintf('TKT-%06d', $ticket->id), $ticket->fresh()->ticket_number);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket.created', 'user_id' => $om->id]);
    }

    public function test_p1_outside_operating_hours_defaults_to_p2_unless_critical_is_confirmed(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        Carbon::setTestNow(Carbon::parse('2026-10-05 03:00:00', 'Asia/Jakarta')); // outlet tutup

        $this->assertSame('P2', $this->openTicket($om, $this->outletA1, $this->posA1)->priority);

        $confirmed = $this->openTicket($om, $this->outletA1, $this->posA1, ['critical_confirmed' => true]);
        $this->assertSame('P1', $confirmed->priority);
        $this->assertTrue($confirmed->critical_confirmed);
    }

    public function test_non_critical_symptom_keeps_its_own_priority(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        $this->assertSame('P3', $this->openTicket($om, $this->outletA1, $this->posA1, ['symptom' => 'slow'])->priority);
    }

    public function test_outlet_manager_cannot_report_for_another_outlet(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        $this->expectException(AuthorizationException::class);
        $this->openTicket($om, $this->outletA2, $this->posA2);
    }

    public function test_user_cannot_report_for_another_company(): void
    {
        $ownerA = $this->makeMember($this->a, Roles::OWNER);

        $this->expectException(AuthorizationException::class);
        $this->openTicket($ownerA, $this->outletB, $this->posB);
    }

    public function test_brand_manager_reports_within_own_brand_only(): void
    {
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA1);

        $this->assertInstanceOf(Ticket::class, $this->openTicket($bm, $this->outletA1, $this->posA1));

        $this->expectException(AuthorizationException::class);
        $this->openTicket($bm, $this->outletA2, $this->posA2);
    }

    public function test_asset_must_match_brand_and_outlet(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        // Aset level brand boleh dipakai untuk tiket di outlet brand tsb.
        $this->assertInstanceOf(Ticket::class, $this->openTicket($owner, $this->outletA1, $this->webA1));

        // Aset milik brand lain ditolak.
        $this->expectException(ValidationException::class);
        $this->openTicket($owner, $this->outletA1, $this->posA2);
    }

    public function test_asset_of_a_sibling_outlet_is_rejected(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);
        $outletA1b = \App\Models\Outlet::factory()->for($this->brandA1)->create();
        $posA1b = $this->makeAsset($this->brandA1, $outletA1b, 'pos', 'POS A1b');

        $this->expectException(ValidationException::class);
        $this->openTicket($owner, $this->outletA1, $posA1b);
    }

    public function test_unknown_category_or_symptom_is_rejected(): void
    {
        $owner = $this->makeMember($this->a, Roles::OWNER);

        try {
            $this->openTicket($owner, $this->outletA1, $this->posA1, ['category' => 'nope']);
            $this->fail('Kategori ngawur seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $this->openTicket($owner, $this->outletA1, $this->posA1, ['symptom' => 'down']); // gejala milik kategori website
    }

    public function test_attachments_are_stored_privately_and_validated(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        $ticket = $this->openTicket($om, $this->outletA1, $this->posA1, [
            'attachments' => [UploadedFile::fake()->image('foto.jpg'), UploadedFile::fake()->create('error.log', 5, 'text/plain')],
        ]);

        $this->assertSame(2, $ticket->attachments()->count());
        foreach (Attachment::withoutGlobalScopes()->get() as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
            $this->assertSame((int) $this->a->id, (int) $attachment->company_id);
        }

        $this->expectException(ValidationException::class);
        $this->openTicket($om, $this->outletA1, $this->posA1, [
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ]);
    }

    public function test_ticket_visibility_follows_membership_scope_and_tenant(): void
    {
        $ownerA = $this->makeMember($this->a, Roles::OWNER);
        $ownerB = $this->makeMember($this->b, Roles::OWNER);
        $bm = $this->makeMember($this->a, Roles::BRAND_MANAGER, $this->brandA1);
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        $tA1 = $this->openTicket($ownerA, $this->outletA1, $this->posA1);
        $tA2 = $this->openTicket($ownerA, $this->outletA2, $this->posA2);
        $tB = $this->openTicket($ownerB, $this->outletB, $this->posB);

        $this->actingAs($ownerA);
        $this->assertEqualsCanonicalizing([$tA1->id, $tA2->id], Ticket::visibleTo($ownerA)->pluck('id')->all());
        $this->assertNull(Ticket::find($tB->id));
        $this->assertSame([$tA1->id], Ticket::visibleTo($bm)->pluck('id')->all());
        $this->assertSame([$tA1->id], Ticket::visibleTo($om)->pluck('id')->all());

        $tBRaw = Ticket::withoutGlobalScopes()->find($tB->id);
        $this->assertFalse(Gate::forUser($ownerA)->allows('view', $tBRaw));
        $this->assertFalse(Gate::forUser($om)->allows('view', $tA2));
        $this->assertTrue(Gate::forUser($bm)->allows('view', $tA1));
    }
}
