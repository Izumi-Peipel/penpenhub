<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        // company_id diturunkan dari brand oleh Asset::resolveCompanyId().
        return [
            'brand_id' => Brand::factory(),
            'outlet_id' => null,
            'type' => 'website',
            'name' => fake()->unique()->words(2, true),
            'endpoint_url' => 'https://example.test',
        ];
    }
}
