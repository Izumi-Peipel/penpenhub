<?php

namespace App\Support;

use App\Models\Company;
use App\Models\QuotaUsage;
use App\Models\Subscription;
use Carbon\Carbon;

/**
 * Service untuk memeriksa dan memotong kuota langganan perusahaan.
 */
final class QuotaManager
{
    /**
     * Ambil subscription aktif suatu perusahaan.
     */
    public function activeSubscription(int|Company $company): ?Subscription
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return Subscription::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('starts_at', '<=', Carbon::today()->toDateString())
            ->where('ends_at', '>=', Carbon::today()->toDateString())
            ->with('plan')
            ->latest('starts_at')
            ->first();
    }

    /**
     * Periksa apakah perusahaan masih memiliki kuota request untuk membuat tiket.
     */
    public function hasRequestQuota(int|Company $company): bool
    {
        $sub = $this->activeSubscription($company);
        if ($sub === null) {
            return false;
        }

        $usage = $sub->currentUsage();
        if ($usage === null) {
            return true; // belum ada pemakaian = masih ada kuota
        }

        return $usage->hasRequestQuota();
    }

    /**
     * Catat pemakaian 1 request (tiket baru dibuat).
     * Mengembalikan QuotaUsage yang diperbarui, atau null jika tidak ada subscription aktif.
     */
    public function incrementRequestUsage(int|Company $company): ?QuotaUsage
    {
        $sub = $this->activeSubscription($company);
        if ($sub === null) {
            return null;
        }

        $usage = $sub->currentOrCreateUsage();
        $usage->increment('requests_used');

        return $usage->fresh();
    }

    /**
     * Tambah pemakaian jam kerja (dalam menit) ke quota usage berjalan.
     */
    public function addHoursUsage(int|Company $company, int $minutes): ?QuotaUsage
    {
        $sub = $this->activeSubscription($company);
        if ($sub === null) {
            return null;
        }

        $usage = $sub->currentOrCreateUsage();
        $usage->increment('hours_used', $minutes);

        return $usage->fresh();
    }

    /**
     * Ringkasan kuota periode berjalan untuk ditampilkan di dashboard.
     *
     * @return array{plan: ?string, requests_used: int, requests_total: int,
     *               hours_used: float, hours_total: int, request_pct: float,
     *               hours_pct: float, period_end: ?string, status: string}
     */
    public function summary(int|Company $company): array
    {
        $sub = $this->activeSubscription($company);

        if ($sub === null) {
            return [
                'plan'           => null,
                'requests_used'  => 0,
                'requests_total' => 0,
                'hours_used'     => 0,
                'hours_total'    => 0,
                'request_pct'    => 0.0,
                'hours_pct'      => 0.0,
                'period_end'     => null,
                'status'         => 'no_subscription',
            ];
        }

        $usage = $sub->currentUsage();

        if ($usage === null) {
            return [
                'plan'           => $sub->plan->name,
                'requests_used'  => 0,
                'requests_total' => $sub->plan->request_quota,
                'hours_used'     => 0,
                'hours_total'    => $sub->plan->hours_quota,
                'request_pct'    => 0.0,
                'hours_pct'      => 0.0,
                'period_end'     => Carbon::today()->endOfMonth()->toDateString(),
                'status'         => 'active',
            ];
        }

        return [
            'plan'           => $sub->plan->name,
            'requests_used'  => $usage->requests_used,
            'requests_total' => $usage->totalRequestQuota(),
            'hours_used'     => round($usage->hours_used / 60, 1),
            'hours_total'    => $usage->totalHoursQuota(),
            'request_pct'    => $usage->requestUsagePercent(),
            'hours_pct'      => $usage->hoursUsagePercent(),
            'period_end'     => $usage->period_end,
            'status'         => 'active',
        ];
    }
}
