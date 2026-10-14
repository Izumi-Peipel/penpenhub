<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Gate;

class Ticket extends Model
{
    use BelongsToCompany;

    public const PRIORITIES = ['P1', 'P2', 'P3', 'P4'];

    protected $fillable = [
        'company_id', 'brand_id', 'outlet_id', 'asset_id', 'reported_by', 'assigned_to',
        'category', 'symptom', 'priority', 'status', 'title', 'description', 'critical_confirmed',
        'responded_at', 'resolved_at', 'closed_at',
        'sla_response_due_at', 'sla_resolution_due_at', 'sla_paused_at',
        'total_paused_seconds', 'sla_response_breached', 'sla_resolution_breached',
    ];

    protected $casts = [
        'critical_confirmed' => 'boolean',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
        'sla_paused_at' => 'datetime',
        'total_paused_seconds' => 'integer',
        'sla_response_breached' => 'boolean',
        'sla_resolution_breached' => 'boolean',
    ];


    protected static function booted(): void
    {
        static::created(function (Ticket $ticket) {
            $ticket->forceFill(['ticket_number' => sprintf('TKT-%06d', $ticket->id)])->saveQuietly();
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withoutGlobalScopes();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class)->withoutGlobalScopes();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isOpen(): bool
    {
        return $this->status !== TicketStatus::DITUTUP;
    }

    public function isSlaPaused(): bool
    {
        return $this->sla_paused_at !== null || $this->status === TicketStatus::MENUNGGU_KLIEN;
    }

    public function isResponseBreached(): bool
    {
        if ($this->sla_response_breached) {
            return true;
        }

        if ($this->sla_response_due_at === null) {
            return false;
        }

        if ($this->responded_at !== null) {
            return $this->responded_at->isAfter($this->sla_response_due_at);
        }

        return now()->isAfter($this->sla_response_due_at);
    }

    public function isResolutionBreached(): bool
    {
        if ($this->sla_resolution_breached) {
            return true;
        }

        if ($this->sla_resolution_due_at === null) {
            return false;
        }

        if ($this->resolved_at !== null) {
            return $this->resolved_at->isAfter($this->sla_resolution_due_at);
        }

        if ($this->isSlaPaused()) {
            return false;
        }

        return now()->isAfter($this->sla_resolution_due_at);
    }

    public function responseSlaStatus(): string
    {
        if ($this->sla_response_due_at === null) {
            return 'none';
        }

        if ($this->responded_at !== null) {
            return $this->responded_at->isAfter($this->sla_response_due_at) ? 'breached' : 'met';
        }

        return now()->isAfter($this->sla_response_due_at) ? 'breached' : 'pending';
    }

    public function resolutionSlaStatus(): string
    {
        if ($this->sla_resolution_due_at === null) {
            return 'none';
        }

        if ($this->resolved_at !== null) {
            return $this->resolved_at->isAfter($this->sla_resolution_due_at) ? 'breached' : 'met';
        }

        if ($this->isSlaPaused()) {
            return 'paused';
        }

        return now()->isAfter($this->sla_resolution_due_at) ? 'breached' : 'pending';
    }


    public function resolveCompanyId(): ?int
    {
        $id = Brand::withoutGlobalScopes()->whereKey($this->brand_id)->value('company_id');

        return $id === null ? null : (int) $id;
    }

    /** Komentar yang boleh dibaca pengguna: catatan internal hanya untuk yang berhak. */
    public function visibleComments(User $user): Collection
    {
        $query = $this->comments()->with('user')->orderBy('created_at')->orderBy('id');

        if (! Gate::forUser($user)->allows('internalNote', $this)) {
            $query->where('is_internal', false);
        }

        return $query->get();
    }

    /** Tiket yang boleh dilihat menurut scope membership (G/B/O). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $memberships = Membership::query()->where('user_id', $user->getKey())->get()
            ->filter(fn (Membership $m) => $m->hasPermission('ticket.view'));

        $companyIds = $memberships->where('scope_type', 'company')->pluck('company_id')->all();
        $brandIds = $memberships->where('scope_type', 'brand')->pluck('scope_id')->all();
        $outletIds = $memberships->where('scope_type', 'outlet')->pluck('scope_id')->all();

        return $query->where(function (Builder $q) use ($companyIds, $brandIds, $outletIds) {
            $q->whereIn('tickets.company_id', $companyIds)
                ->orWhereIn('tickets.brand_id', $brandIds)
                ->orWhereIn('tickets.outlet_id', $outletIds);
        });
    }
}
