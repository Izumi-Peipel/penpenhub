<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Angka usulan PRD bagian 8; harga dan kuota Enterprise masih "(konfirmasi)". */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'basic', 'name' => 'Basic', 'price_monthly' => 2500000, 'price_yearly' => 25500000, 'hours_quota' => 8, 'request_quota' => 5, 'addon_hour_price' => 450000, 'addon_request_price' => 150000, 'uptime_monitor_limit' => 0],
            ['code' => 'pro', 'name' => 'Pro', 'price_monthly' => 6500000, 'price_yearly' => 66300000, 'hours_quota' => 24, 'request_quota' => 20, 'addon_hour_price' => 400000, 'addon_request_price' => 125000, 'uptime_monitor_limit' => 10],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'price_monthly' => 15000000, 'price_yearly' => 153000000, 'hours_quota' => 60, 'request_quota' => 60, 'addon_hour_price' => 350000, 'addon_request_price' => 100000, 'uptime_monitor_limit' => 30],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan + ['is_active' => true]);
        }
    }
}
