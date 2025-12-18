<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['code' => 'HAREM', 'name' => 'Harem Altın', 'is_active' => true],
            ['code' => 'AHLATCI', 'name' => 'Ahlatcı Döviz', 'is_active' => true],
            ['code' => 'NADIR', 'name' => 'Nadir Döviz', 'is_active' => true],
        ];

        foreach ($sources as $source) {
            Source::query()->updateOrCreate(
                ['code' => $source['code']],
                $source
            );
        }
    }
}

