<?php

namespace App\Actions;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\User;
use App\Rules\ValidOperatingHours;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ManageOutlet
{
    public function create(User $actor, Brand $brand, array $input): Outlet
    {
        Gate::forUser($actor)->authorize('create', [Outlet::class, $brand]);

        $data = Validator::make($input, $this->rules($brand->id))->validate();

        $outlet = Outlet::create($data + ['brand_id' => $brand->id, 'company_id' => $brand->company_id]);

        AuditLogger::log('outlet.created', $outlet, ['name' => $outlet->name], $outlet->company_id, $actor);

        return $outlet;
    }

    public function update(User $actor, Outlet $outlet, array $input): Outlet
    {
        Gate::forUser($actor)->authorize('update', $outlet);

        $data = Validator::make($input, $this->rules($outlet->brand_id, $outlet->id))->validate();

        $outlet->fill($data)->save();

        AuditLogger::log('outlet.updated', $outlet, ['changes' => $outlet->getChanges()], $outlet->company_id, $actor);

        return $outlet;
    }

    private function rules(int $brandId, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('outlets', 'name')->where('brand_id', $brandId)->ignore($ignoreId)],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['required', 'timezone'],
            'operating_hours' => ['required', 'array', new ValidOperatingHours()],
        ];
    }
}
