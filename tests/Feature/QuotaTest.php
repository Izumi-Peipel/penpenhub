<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\QuotaUsage;
use App\Models\Subscription;
use App\Support\QuotaManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private Plan $planPro;
    private Subscription $subA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();

        // Buat plan Pro
        $this->planPro = Plan::create([
            'code'                => 'pro',
            'name'                => 'Pro',
            'price_monthly'       => 6500000,
            'price_yearly'        => 66300000,
            'hours_quota'         => 24,
            'request_quota'       => 20,
            'addon_hour_price'    => 400000,
            'addon_request_price' => 125000,
            'uptime_monitor_limit'=> 10,
            'is_active'           => true,
        ]);

        // Subscription aktif bulan ini untuk perusahaan A
        $this->subA = Subscription::create([
            'company_id'    => $this->a->id,
            'plan_id'       => $this->planPro->id,
            'billing_cycle' => 'monthly',
            'starts_at'     => Carbon::today()->startOfMonth()->toDateString(),
            'ends_at'       => Carbon::today()->endOfMonth()->toDateString(),
            'status'        => 'active',
        ]);
    }

    public function test_company_with_active_subscription_has_request_quota(): void
    {
        $quota = app(QuotaManager::class);

        $this->assertTrue($quota->hasRequestQuota($this->a->id));
    }

    public function test_company_without_subscription_has_no_quota(): void
    {
        $quota = app(QuotaManager::class);

        // Perusahaan B tidak punya subscription
        $this->assertFalse($quota->hasRequestQuota($this->b->id));
    }

    public function test_creating_ticket_increments_request_usage(): void
    {
        $om = $this->makeMember($this->a, \App\Support\Roles::OUTLET_MANAGER, $this->outletA1);

        $this->openTicket($om, $this->outletA1, $this->posA1);

        $usage = $this->subA->currentUsage();
        $this->assertNotNull($usage);
        $this->assertSame(1, $usage->requests_used);
    }

    public function test_multiple_tickets_accumulate_usage(): void
    {
        $om = $this->makeMember($this->a, \App\Support\Roles::OUTLET_MANAGER, $this->outletA1);

        $this->openTicket($om, $this->outletA1, $this->posA1);
        $this->openTicket($om, $this->outletA1, $this->posA1);
        $this->openTicket($om, $this->outletA1, $this->posA1);

        $usage = $this->subA->fresh()->currentUsage();
        $this->assertSame(3, $usage->requests_used);
    }

    public function test_quota_summary_returns_correct_data(): void
    {
        $om = $this->makeMember($this->a, \App\Support\Roles::OUTLET_MANAGER, $this->outletA1);
        $this->openTicket($om, $this->outletA1, $this->posA1);
        $this->openTicket($om, $this->outletA1, $this->posA1);

        $summary = app(QuotaManager::class)->summary($this->a->id);

        $this->assertSame('Pro', $summary['plan']);
        $this->assertSame(2, $summary['requests_used']);
        $this->assertSame(20, $summary['requests_total']);
        $this->assertSame('active', $summary['status']);
        $this->assertSame(10.0, $summary['request_pct']);
    }

    public function test_quota_summary_without_subscription_shows_no_subscription(): void
    {
        $summary = app(QuotaManager::class)->summary($this->b->id);

        $this->assertSame('no_subscription', $summary['status']);
        $this->assertNull($summary['plan']);
    }

    public function test_expired_subscription_is_not_counted_as_active(): void
    {
        // Buat subscription yang sudah expired kemarin
        Subscription::create([
            'company_id'    => $this->b->id,
            'plan_id'       => $this->planPro->id,
            'billing_cycle' => 'monthly',
            'starts_at'     => Carbon::today()->subMonth()->startOfMonth()->toDateString(),
            'ends_at'       => Carbon::today()->subDay()->toDateString(),
            'status'        => 'active',
        ]);

        $this->assertFalse(app(QuotaManager::class)->hasRequestQuota($this->b->id));
    }

    public function test_dashboard_shows_quota_for_logged_in_user(): void
    {
        $om = $this->makeMember($this->a, \App\Support\Roles::OUTLET_MANAGER, $this->outletA1);

        // Gunakan session key yang benar sesuai CurrentContext::SESSION_KEY
        $response = $this->actingAs($om)
            ->withSession([\App\Support\CurrentContext::SESSION_KEY => $this->a->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pro');
        $response->assertSee('Request Tiket');
        $response->assertSee('Jam Layanan');
    }

    public function test_add_hours_usage_records_minutes_correctly(): void
    {
        $quota = app(QuotaManager::class);

        // Tambah 90 menit (= 1.5 jam)
        $usage = $quota->addHoursUsage($this->a->id, 90);

        $this->assertNotNull($usage);
        $this->assertSame(90, $usage->hours_used);
        // Persentase: 90 menit / 60 = 1.5 jam dari total 24 jam = 6.25%
        $this->assertSame(1.5, round($usage->hours_used / 60, 1));
    }
}
