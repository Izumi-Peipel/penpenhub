<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'cuisine_genre'];

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    /**
     * Brand yang boleh dilihat pengguna menurut scope membership (G/B/O).
     * Global scope hanya membatasi perusahaan; gunakan ini untuk daftar bagi Brand/Outlet Manager.
     */
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
            $q->whereIn('brands.company_id', $companyIds)
                ->orWhereIn('brands.id', $brandIds)
                ->orWhereIn('brands.id', Outlet::withoutGlobalScopes()->whereIn('id', $outletIds)->select('brand_id'));
        });
    }
}
