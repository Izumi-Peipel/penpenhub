<?php

namespace Tests\Concerns;

use App\Actions\CreateTicket;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Dunia uji: dua perusahaan; A punya 2 brand/outlet dan aset, B punya 1 set. Waktu dibekukan saat jam buka. */
trait BuildsWorld
{
    use BuildsTenants;

    protected Company $a;
    protected Company $b;
    protected Brand $brandA1;
    protected Brand $brandA2;
    protected Brand $brandB;
    protected Outlet $outletA1;
    protected Outlet $outletA2;
    protected Outlet $outletB;
    protected Asset $posA1;
    protected Asset $webA1;
    protected Asset $posA2;
    protected Asset $posB;

    protected function setUpWorld(): void
    {
        $this->setUpTenants();
        Storage::fake('local');
        // Senin 12:00 WIB: semua outlet pabrikan (10:00-22:00 tiap hari) sedang buka.
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));

        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $this->brandA1 = Brand::factory()->create(['company_id' => $this->a->id]);
        $this->brandA2 = Brand::factory()->create(['company_id' => $this->a->id]);
        $this->brandB = Brand::factory()->create(['company_id' => $this->b->id]);
        $this->outletA1 = Outlet::factory()->for($this->brandA1)->create();
        $this->outletA2 = Outlet::factory()->for($this->brandA2)->create();
        $this->outletB = Outlet::factory()->for($this->brandB)->create();

        $this->posA1 = $this->makeAsset($this->brandA1, $this->outletA1, 'pos', 'POS Kasir A1');
        $this->webA1 = $this->makeAsset($this->brandA1, null, 'website', 'Website A1');
        $this->posA2 = $this->makeAsset($this->brandA2, $this->outletA2, 'pos', 'POS Kasir A2');
        $this->posB = $this->makeAsset($this->brandB, $this->outletB, 'pos', 'POS Kasir B');
    }

    protected function makeAsset(Brand $brand, ?Outlet $outlet, string $type, string $name): Asset
    {
        return Asset::factory()->create([
            'brand_id' => $brand->id,
            'outlet_id' => $outlet?->id,
            'type' => $type,
            'name' => $name,
            'endpoint_url' => $type === 'pos' ? null : 'https://example.test',
        ]);
    }

    protected function ticketInput(Outlet $outlet, Asset $asset, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $outlet->brand_id,
            'outlet_id' => $outlet->id,
            'asset_id' => $asset->id,
            'category' => 'pos',
            'symptom' => 'cannot_transact',
            'description' => 'Kasir tidak bisa transaksi sama sekali',
        ], $overrides);
    }

    protected function openTicket(User $reporter, Outlet $outlet, Asset $asset, array $overrides = []): Ticket
    {
        return app(CreateTicket::class)->handle($reporter, $this->ticketInput($outlet, $asset, $overrides));
    }
}
