<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => 'GRAM_24', 'name' => 'Gram Altın (24 Ayar)'],
            ['code' => 'GRAM_22', 'name' => 'Gram Altın (22 Ayar)'],
            ['code' => 'CEYREK', 'name' => 'Çeyrek Altın'],
            ['code' => 'YARIM', 'name' => 'Yarım Altın'],
            ['code' => 'TAM', 'name' => 'Tam Altın'],
            ['code' => 'ATA', 'name' => 'Ata Altın'],
            ['code' => 'RESAT', 'name' => 'Reşat Altın'],
            ['code' => 'GREMSE', 'name' => 'Gremse Altın'],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['code' => $product['code']],
                $product
            );
        }
    }
}

