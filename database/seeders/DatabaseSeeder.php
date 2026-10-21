<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );

        \App\Models\Task::firstOrCreate(
            ['title' => 'Learn Laravel 12 API development'],
            ['description' => 'Understand routes, controllers, and Sanctum tokens.', 'is_completed' => true]
        );

        \App\Models\Task::firstOrCreate(
            ['title' => 'Build frontend integration'],
            ['description' => 'Connect React/Inertia or external client to the API.', 'is_completed' => false]
        );

        $this->call([
            CategorySeeder::class,
            AccountSeeder::class,
        ]);
    }
}
