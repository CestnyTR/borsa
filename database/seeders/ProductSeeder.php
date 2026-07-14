<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // has_factor: ürünün saf gram-altın içeriği (yaklaşık). Fiyat sunucuda
        // HAS × has_factor × mağaza marjı olarak hesaplanır.
        $products = [
            ['code' => 'GRAM_24', 'name' => 'Gram Altın (24 Ayar)', 'has_factor' => 1.000],
            ['code' => 'GRAM_22', 'name' => 'Gram Altın (22 Ayar)', 'has_factor' => 0.916],
            ['code' => 'CEYREK', 'name' => 'Çeyrek Altın', 'has_factor' => 1.600],
            ['code' => 'YARIM', 'name' => 'Yarım Altın', 'has_factor' => 3.200],
            ['code' => 'TAM', 'name' => 'Tam Altın', 'has_factor' => 6.400],
            ['code' => 'ATA', 'name' => 'Ata Altın', 'has_factor' => 6.610],
            ['code' => 'RESAT', 'name' => 'Reşat Altın', 'has_factor' => 6.600],
            ['code' => 'GREMSE', 'name' => 'Gremse Altın', 'has_factor' => 6.420],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['code' => $product['code']],
                $product
            );
        }
    }
}

