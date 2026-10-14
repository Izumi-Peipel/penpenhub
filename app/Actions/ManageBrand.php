<?php

namespace App\Actions;

use App\Models\Brand;
use App\Models\Company;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ManageBrand
{
    public function create(User $actor, Company $company, array $input): Brand
    {
        Gate::forUser($actor)->authorize('create', [Brand::class, $company]);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->where('company_id', $company->id)],
            'cuisine_genre' => ['nullable', 'string', 'max:60'],
        ])->validate();

        $brand = Brand::create($data + ['company_id' => $company->id]);

        AuditLogger::log('brand.created', $brand, ['name' => $brand->name], $company->id, $actor);

        return $brand;
    }

    public function update(User $actor, Brand $brand, array $input): Brand
    {
        Gate::forUser($actor)->authorize('update', $brand);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->where('company_id', $brand->company_id)->ignore($brand->id)],
            'cuisine_genre' => ['nullable', 'string', 'max:60'],
        ])->validate();

        $brand->fill($data)->save();

        AuditLogger::log('brand.updated', $brand, ['changes' => $brand->getChanges()], $brand->company_id, $actor);

        return $brand;
    }
}
