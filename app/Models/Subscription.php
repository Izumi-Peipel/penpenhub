<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'company_id', 'plan_id', 'billing_cycle', 'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function quotaUsages(): HasMany
    {
        return $this->hasMany(QuotaUsage::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->greaterThanOrEqualTo(Carbon::today());
    }

    /** Kuota usage untuk periode yang mencakup tanggal tertentu (default hari ini). */
    public function currentUsage(?Carbon $date = null): ?QuotaUsage
    {
        $date ??= Carbon::today();

        return $this->quotaUsages()
            ->where('period_start', '<=', $date->toDateString())
            ->where('period_end', '>=', $date->toDateString())
            ->first();
    }

    /** Buat atau ambil quota_usage untuk periode berjalan. */
    public function currentOrCreateUsage(?Carbon $date = null): QuotaUsage
    {
        $date ??= Carbon::today();

        $periodStart = $date->copy()->startOfMonth()->toDateString();
        $periodEnd   = $date->copy()->endOfMonth()->toDateString();

        return $this->quotaUsages()->firstOrCreate(
            ['period_start' => $periodStart],
            [
                'company_id'  => $this->company_id,
                'period_end'  => $periodEnd,
            ]
        );
    }
}
