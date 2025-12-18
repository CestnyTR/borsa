<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SourceSeeder::class,
            ProductSeeder::class,
            StoreSeeder::class,
            DemoMultiplierSeeder::class,
        ]);

        User::query()->updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);
    }
}
