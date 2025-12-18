<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        Store::query()->updateOrCreate(
            ['name' => 'Demo Mağaza'],
            ['is_active' => true]
        );
    }
}

