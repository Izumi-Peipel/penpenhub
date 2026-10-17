<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaUsage extends Model
{
    protected $fillable = [
        'subscription_id', 'company_id', 'period_start', 'period_end',
        'hours_used', 'requests_used', 'addon_hours', 'addon_requests',
    ];

    protected $casts = [
        'period_start'    => 'string',
        'period_end'      => 'string',
        'hours_used'      => 'integer',
        'requests_used'   => 'integer',
        'addon_hours'     => 'integer',
        'addon_requests'  => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    /** Total kuota request yang tersedia (paket + add-on). */
    public function totalRequestQuota(): int
    {
        $plan = $this->subscription?->plan;
        return (int) ($plan?->request_quota ?? 0) + $this->addon_requests;
    }

    /** Total kuota jam (menit) yang tersedia (paket + add-on). */
    public function totalHoursQuota(): int
    {
        $plan = $this->subscription?->plan;
        return (int) ($plan?->hours_quota ?? 0) + $this->addon_hours;
    }

    /** Sisa kuota request. */
    public function remainingRequests(): int
    {
        return max(0, $this->totalRequestQuota() - $this->requests_used);
    }

    /** Apakah masih ada sisa request quota. */
    public function hasRequestQuota(): bool
    {
        return $this->remainingRequests() > 0;
    }

    /** Persentase pemakaian request (0-100). */
    public function requestUsagePercent(): float
    {
        $total = $this->totalRequestQuota();
        return $total > 0 ? min(100.0, round(($this->requests_used / $total) * 100, 1)) : 0.0;
    }

    /** Sisa kuota jam (dalam jam, bukan menit). */
    public function remainingHours(): float
    {
        $remaining = max(0, $this->totalHoursQuota() - (int) ($this->hours_used / 60));
        return $remaining;
    }

    /** Persentase pemakaian jam (0-100). */
    public function hoursUsagePercent(): float
    {
        $total = $this->totalHoursQuota();
        $used = (int) ($this->hours_used / 60);
        return $total > 0 ? min(100.0, round(($used / $total) * 100, 1)) : 0.0;
    }
}
