<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Plan;
use App\Support\PrioritySuggester;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class PlansAndPrioritiesTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_plans_seed_matches_the_prd_proposal_and_is_idempotent(): void
    {
        $this->seed(PlansSeeder::class);
        $this->seed(PlansSeeder::class);

        $this->assertSame(3, Plan::count());
        $pro = Plan::where('code', 'pro')->firstOrFail();
        $this->assertSame(20, $pro->request_quota);
        $this->assertSame(24, $pro->hours_quota);
        $this->assertSame(10, $pro->uptime_monitor_limit);
        $this->assertSame(0, Plan::where('code', 'basic')->value('uptime_monitor_limit'));
        $this->assertSame(60, Plan::where('code', 'enterprise')->value('request_quota'));
    }

    public function test_priority_rules(): void
    {
        $this->setUpWorld();
        $open = Carbon::parse('2026-10-05 12:00', 'Asia/Jakarta');
        $closed = Carbon::parse('2026-10-05 03:00', 'Asia/Jakarta');
        $outlet = Outlet::withoutGlobalScopes()->find($this->outletA1->id);

        $this->assertSame('P1', PrioritySuggester::suggest('pos', 'cannot_transact', $outlet, $open));
        $this->assertSame('P2', PrioritySuggester::suggest('pos', 'cannot_transact', $outlet, $closed));
        $this->assertSame('P1', PrioritySuggester::suggest('pos', 'cannot_transact', $outlet, $closed, true));
        $this->assertSame('P2', PrioritySuggester::suggest('payment', 'qris_failed', $outlet, $closed));
        $this->assertSame('P3', PrioritySuggester::suggest('pos', 'tidak-dikenal', $outlet, $open)); // default kategori
        $this->assertTrue(PrioritySuggester::needsCriticalConfirmation('pos', 'cannot_transact', $outlet, $closed));
        $this->assertFalse(PrioritySuggester::needsCriticalConfirmation('pos', 'slow', $outlet, $closed));
        $this->assertFalse(PrioritySuggester::needsCriticalConfirmation('pos', 'cannot_transact', null, $closed));
    }
}
