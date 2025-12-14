<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyGoldSetting;
use App\Models\GoldAsset;
use App\Models\GoldSource;
use Illuminate\Database\Seeder;

class GoldSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create gold sources (3 websites)
        $sources = [
            ['name' => 'Altın Piyasası', 'code' => 'ALTIN_PIYASASI', 'base_url' => 'https://www.altinpiyasasi.com', 'is_active' => true],
            ['name' => 'Kuyumcu Portal', 'code' => 'KUYUMCU_PORTAL', 'base_url' => 'https://www.kuyumcuportal.com', 'is_active' => true],
            ['name' => 'Altın Fiyatları', 'code' => 'ALTIN_FIYATLARI', 'base_url' => 'https://www.altinfiyatlari.com', 'is_active' => true],
        ];

        foreach ($sources as $source) {
            GoldSource::firstOrCreate(['code' => $source['code']], $source);
        }

        // Create gold asset (gram gold)
        $asset = GoldAsset::firstOrCreate(
            ['code' => 'GRAM_GOLD_TRY'],
            [
                'name' => 'Gram Altın',
                'unit' => 'gram',
                'is_active' => true,
            ]
        );

        // Create companies
        $companyA = Company::firstOrCreate(
            ['code' => 'KUYUMCU_A'],
            [
                'name' => 'Kuyumcu A',
                'is_active' => true,
            ]
        );

        $companyB = Company::firstOrCreate(
            ['code' => 'KUYUMCU_B'],
            [
                'name' => 'Kuyumcu B',
                'is_active' => true,
            ]
        );

        // Company A settings (0.25g, 0.50g, 1g, 5g with 2% markup)
        $companyASettings = [
            ['weight_gram' => 0.25, 'markup_percent' => 2.00, 'sort_order' => 1],
            ['weight_gram' => 0.50, 'markup_percent' => 2.00, 'sort_order' => 2],
            ['weight_gram' => 1.00, 'markup_percent' => 2.00, 'sort_order' => 3],
            ['weight_gram' => 5.00, 'markup_percent' => 2.00, 'sort_order' => 4],
        ];

        foreach ($companyASettings as $setting) {
            CompanyGoldSetting::firstOrCreate(
                [
                    'company_id' => $companyA->id,
                    'asset_id' => $asset->id,
                    'weight_gram' => $setting['weight_gram'],
                ],
                [
                    'markup_percent' => $setting['markup_percent'],
                    'markup_fixed' => 0,
                    'rounding_step' => 0.10,
                    'is_active' => true,
                    'sort_order' => $setting['sort_order'],
                ]
            );
        }

        // Company B settings (1g, 2g, 10g with 5 TL fixed markup)
        $companyBSettings = [
            ['weight_gram' => 1.00, 'markup_fixed' => 5.00, 'sort_order' => 1],
            ['weight_gram' => 2.00, 'markup_fixed' => 8.00, 'sort_order' => 2],
            ['weight_gram' => 10.00, 'markup_fixed' => 30.00, 'sort_order' => 3],
        ];

        foreach ($companyBSettings as $setting) {
            CompanyGoldSetting::firstOrCreate(
                [
                    'company_id' => $companyB->id,
                    'asset_id' => $asset->id,
                    'weight_gram' => $setting['weight_gram'],
                ],
                [
                    'markup_percent' => 0,
                    'markup_fixed' => $setting['markup_fixed'],
                    'rounding_step' => 1.00,
                    'is_active' => true,
                    'sort_order' => $setting['sort_order'],
                ]
            );
        }
    }
}
