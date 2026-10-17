<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles & permissions (harus pertama)
        $this->call(RolesAndPermissionsSeeder::class);

        // Paket langganan
        $this->call(PlansSeeder::class);

        // User super admin untuk akses panel
        User::factory()->create([
            'name'        => 'Admin PenpenHub',
            'email'       => 'admin@penpenhub.id',
            'is_internal' => true,
        ]);

        // User klien demo
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
