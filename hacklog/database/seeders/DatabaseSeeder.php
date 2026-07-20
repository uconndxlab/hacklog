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
        // Create test users for authentication and task assignment
        if (!User::where('email', 'admin@hacklog.com')->exists()) {
            User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@hacklog.com',
                'role' => User::ROLE_ADMIN,
            ]);
        }

        if (!User::where('email', 'jane@hacklog.com')->exists()) {
            User::factory()->create([
                'name' => 'Jane Developer',
                'email' => 'jane@hacklog.com',
                'role' => User::ROLE_TEAM,
            ]);
        }

        if (!User::where('email', 'john@hacklog.com')->exists()) {
            User::factory()->create([
                'name' => 'John Designer',
                'email' => 'john@hacklog.com',
                'role' => User::ROLE_TEAM,
            ]);
        }
    }
}
