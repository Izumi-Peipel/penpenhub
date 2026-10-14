<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Outlet;
use App\Support\OperatingHours;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Outlet> */
class OutletFactory extends Factory
{
    public function definition(): array
    {
        // company_id sengaja tidak diisi: diturunkan dari brand oleh Outlet::resolveCompanyId().
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->unique()->streetName(),
            'address' => fake()->address(),
            'operating_hours' => array_fill_keys(OperatingHours::DAYS, [['10:00', '22:00']]),
            'timezone' => 'Asia/Jakarta',
        ];
    }
}
