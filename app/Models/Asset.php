<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\AssetTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'brand_id', 'outlet_id', 'type', 'name', 'endpoint_url'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function typeLabel(): string
    {
        return AssetTypes::labels()[$this->type] ?? $this->type;
    }

    public function resolveCompanyId(): ?int
    {
        $id = Brand::withoutGlobalScopes()->whereKey($this->brand_id)->value('company_id');

        return $id === null ? null : (int) $id;
    }

    /** Aset level brand (outlet_id null) terlihat oleh Outlet Manager dari brand tsb. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $memberships = Membership::query()->where('user_id', $user->getKey())->get()
            ->filter(fn (Membership $m) => $m->hasPermission('structure.view'));

        $companyIds = $memberships->where('scope_type', 'company')->pluck('company_id')->all();
        $brandIds = $memberships->where('scope_type', 'brand')->pluck('scope_id')->all();
        $outletIds = $memberships->where('scope_type', 'outlet')->pluck('scope_id')->all();

        return $query->where(function (Builder $q) use ($companyIds, $brandIds, $outletIds) {
            $q->whereIn('assets.company_id', $companyIds)
                ->orWhereIn('assets.brand_id', $brandIds)
                ->orWhereIn('assets.outlet_id', $outletIds)
                ->orWhere(function (Builder $q2) use ($outletIds) {
                    $q2->whereNull('assets.outlet_id')
                        ->whereIn('assets.brand_id', Outlet::withoutGlobalScopes()->whereIn('id', $outletIds)->select('brand_id'));
                });
        });
    }
}
