<?php

namespace Tests\Feature;

use App\Livewire\QuickTicket;
use App\Models\Ticket;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class QuickTicketTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();
    }

    public function test_three_steps_create_a_p1_ticket_with_a_photo(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        Livewire::actingAs($om)->test(QuickTicket::class)
            ->assertSet('outletId', (int) $this->outletA1->id) // satu-satunya outlet: terisi otomatis
            ->set('assetId', $this->posA1->id)
            ->call('next')->assertSet('step', 2)
            ->set('category', 'pos')->set('symptom', 'cannot_transact')
            ->assertSet('criticalConfirmed', false)
            ->call('next')->assertSet('step', 3)
            ->set('description', 'Kasir tidak bisa transaksi sejak 12.00')
            ->set('photos', [UploadedFile::fake()->image('layar.jpg')])
            ->call('submit')
            ->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('P1', $ticket->priority);
        $this->assertSame((int) $om->id, (int) $ticket->reported_by);
        $this->assertSame(1, $ticket->attachments()->count());
    }

    public function test_each_step_validates_before_advancing(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        Livewire::actingAs($om)->test(QuickTicket::class)
            ->call('next')->assertHasErrors('assetId')->assertSet('step', 1)
            ->set('assetId', $this->posA1->id)->call('next')->assertSet('step', 2)
            ->call('next')->assertHasErrors('category')->assertSet('step', 2);
    }

    public function test_back_returns_to_the_previous_step(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        Livewire::actingAs($om)->test(QuickTicket::class)
            ->set('assetId', $this->posA1->id)->call('next')->call('back')->assertSet('step', 1);
    }

    public function test_outside_hours_needs_confirmation_and_otherwise_falls_back_to_p2(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        Carbon::setTestNow(Carbon::parse('2026-10-05 03:00:00', 'Asia/Jakarta'));

        Livewire::actingAs($om)->test(QuickTicket::class)
            ->set('assetId', $this->posA1->id)->call('next')
            ->set('category', 'pos')->set('symptom', 'cannot_transact')
            ->assertSet('suggestedPriority', 'P2')
            ->set('criticalConfirmed', true)
            ->assertSet('suggestedPriority', 'P1')
            ->call('next')
            ->set('description', 'Kasir mati, ada event malam ini')
            ->call('submit')->assertRedirect();

        $this->assertSame('P1', Ticket::withoutGlobalScopes()->firstOrFail()->priority);
    }

    public function test_user_cannot_pick_an_asset_from_another_outlet(): void
    {
        $om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);

        Livewire::actingAs($om)->test(QuickTicket::class)
            ->set('assetId', $this->posA2->id) // aset outlet lain
            ->call('next')
            ->set('category', 'pos')->set('symptom', 'slow')->call('next')
            ->set('description', 'Percobaan lintas outlet')
            ->call('submit')
            ->assertHasErrors('asset_id');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->count());
    }

    public function test_page_requires_login(): void
    {
        $this->get(route('tickets.new'))->assertRedirect(route('login'));
    }
}
