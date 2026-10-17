<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Buat subscription aktif bulan ini untuk setiap perusahaan yang belum berlangganan.
 * Berguna untuk development dan testing manual.
 */
class SubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('code', 'pro')->first();

        if (! $plan) {
            $this->command->warn('PlansSeeder belum dijalankan. Jalankan PlansSeeder terlebih dahulu.');
            return;
        }

        $now = Carbon::today();

        Company::withoutGlobalScopes()->get()->each(function (Company $company) use ($plan, $now) {
            $exists = Subscription::where('company_id', $company->id)
                ->where('status', 'active')
                ->where('ends_at', '>=', $now->toDateString())
                ->exists();

            if (! $exists) {
                Subscription::create([
                    'company_id'    => $company->id,
                    'plan_id'       => $plan->id,
                    'billing_cycle' => 'monthly',
                    'starts_at'     => $now->copy()->startOfMonth()->toDateString(),
                    'ends_at'       => $now->copy()->endOfMonth()->toDateString(),
                    'status'        => 'active',
                ]);

                $this->command->info("Subscription Pro dibuat untuk: {$company->name}");
            }
        });
    }
}
