<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\OperatingHours;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outlet extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'brand_id', 'name', 'address', 'operating_hours', 'timezone'];

    protected $casts = ['operating_hours' => 'array'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Dipanggil trait BelongsToCompany saat company_id belum diisi. */
    public function resolveCompanyId(): ?int
    {
        $id = Brand::withoutGlobalScopes()->whereKey($this->brand_id)->value('company_id');

        return $id === null ? null : (int) $id;
    }

    public function operatingHours(): OperatingHours
    {
        return new OperatingHours($this->operating_hours ?? []);
    }

    public function isOpenAt(CarbonInterface $at): bool
    {
        return $this->operatingHours()->isOpenAt($at, $this->timezone ?: 'Asia/Jakarta');
    }

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
            $q->whereIn('outlets.company_id', $companyIds)
                ->orWhereIn('outlets.brand_id', $brandIds)
                ->orWhereIn('outlets.id', $outletIds);
        });
    }
}
