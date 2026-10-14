<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Pemilih perusahaan/brand/outlet (F-104). Hanya perusahaan yang mempersempit scope data
 * (lewat CurrentContext); brand/outlet aktif dipakai sebagai filter dan nilai awal formulir.
 */
final class ContextSwitcher
{
    public const BRAND_KEY = 'active_brand_id';
    public const OUTLET_KEY = 'active_outlet_id';

    public function __construct(private readonly CurrentContext $context)
    {
    }

    /** @return Collection<int, Company> */
    public function companies(User $user): Collection
    {
        $ids = $this->context->allowedCompanyIds($user);

        return Company::withoutGlobalScopes()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{company_id: ?int, brand_id: ?int, outlet_id: ?int, companies: Collection, brands: Collection, outlets: Collection}
     */
    public function state(User $user): array
    {
        $companies = $this->companies($user);
        $companyId = $this->resolve(session(CurrentContext::SESSION_KEY), $companies->pluck('id'));

        $brands = Brand::withoutGlobalScopes()->visibleTo($user)
            ->when($companyId, fn ($q) => $q->where('brands.company_id', $companyId))
            ->orderBy('name')->get();
        $brandId = $this->resolve(session(self::BRAND_KEY), $brands->pluck('id'));

        $outlets = Outlet::withoutGlobalScopes()->visibleTo($user)
            ->when($companyId, fn ($q) => $q->where('outlets.company_id', $companyId))
            ->when($brandId, fn ($q) => $q->where('outlets.brand_id', $brandId))
            ->orderBy('name')->get();
        $outletId = $this->resolve(session(self::OUTLET_KEY), $outlets->pluck('id'));

        return compact('companyId', 'brandId', 'outletId', 'companies', 'brands', 'outlets') + [
            'company_id' => $companyId, 'brand_id' => $brandId, 'outlet_id' => $outletId,
        ];
    }

    /** Pilih level terdalam yang diberikan; level di atasnya diturunkan, level di bawahnya dikosongkan. */
    public function select(User $user, ?int $companyId, ?int $brandId, ?int $outletId): void
    {
        if ($outletId) {
            $outlet = Outlet::withoutGlobalScopes()->visibleTo($user)->whereKey($outletId)->first()
                ?? throw ValidationException::withMessages(['outlet_id' => 'Outlet tidak tersedia.']);
            $brandId = (int) $outlet->brand_id;
            $companyId = (int) $outlet->company_id;
        } elseif ($brandId) {
            $brand = Brand::withoutGlobalScopes()->visibleTo($user)->whereKey($brandId)->first()
                ?? throw ValidationException::withMessages(['brand_id' => 'Brand tidak tersedia.']);
            $companyId = (int) $brand->company_id;
            $outletId = null;
        } else {
            $brandId = $outletId = null;
        }

        if ($companyId) {
            $this->context->setActive($user, $companyId);
        } else {
            session()->forget(CurrentContext::SESSION_KEY);
        }

        $brandId ? session([self::BRAND_KEY => $brandId]) : session()->forget(self::BRAND_KEY);
        $outletId ? session([self::OUTLET_KEY => $outletId]) : session()->forget(self::OUTLET_KEY);
    }

    private function resolve(mixed $stored, Collection $ids): ?int
    {
        if ($stored !== null && $ids->contains((int) $stored)) {
            return (int) $stored;
        }

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
