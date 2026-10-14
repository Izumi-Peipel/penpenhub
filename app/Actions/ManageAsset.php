<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AssetTypes;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ManageAsset
{
    public function create(User $actor, Brand $brand, array $input): Asset
    {
        Gate::forUser($actor)->authorize('create', [Asset::class, $brand]);

        $data = $this->validated($brand->id, $input);
        $asset = Asset::create($data + ['brand_id' => $brand->id, 'company_id' => $brand->company_id]);

        AuditLogger::log('asset.created', $asset, ['type' => $asset->type, 'name' => $asset->name], $asset->company_id, $actor);

        return $asset;
    }

    public function update(User $actor, Asset $asset, array $input): Asset
    {
        Gate::forUser($actor)->authorize('update', $asset);

        $asset->fill($this->validated($asset->brand_id, $input))->save();

        AuditLogger::log('asset.updated', $asset, ['changes' => $asset->getChanges()], $asset->company_id, $actor);

        return $asset;
    }

    public function delete(User $actor, Asset $asset): void
    {
        Gate::forUser($actor)->authorize('delete', $asset);

        if (Ticket::withoutGlobalScopes()->where('asset_id', $asset->id)->exists()) {
            throw ValidationException::withMessages(['asset' => 'Aset masih dipakai tiket dan tidak bisa dihapus.']);
        }

        $asset->delete();

        AuditLogger::log('asset.deleted', $asset, ['name' => $asset->name], $asset->company_id, $actor);
    }

    private function validated(int $brandId, array $input): array
    {
        return Validator::make($input, [
            'type' => ['required', Rule::in(AssetTypes::keys())],
            'name' => ['required', 'string', 'max:120'],
            'outlet_id' => ['nullable', 'integer', Rule::exists('outlets', 'id')->where('brand_id', $brandId)],
            // Hanya http/https: URL ini kelak dipakai pemantauan uptime.
            'endpoint_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ])->validate();
    }
}
