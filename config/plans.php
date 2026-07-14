<?php

return [

    // Aboneliği olmayan / süresi geçen kullanıcı bu plana düşer.
    'default' => 'free',

    'plans' => [

        'free' => [
            'code' => 'free',
            'name' => 'Ücretsiz',
            'price' => 0,
            'currency' => 'TRY',
            'interval' => 'month',
            'features' => [
                'max_stores' => 1,
                'live_prices' => false,   // gecikmeli (dakikalık)
                'branded_board' => false,
                'custom_domain' => false,
            ],
            'highlights' => [
                '1 mağaza',
                'Gecikmeli fiyat (dakikalık)',
                'Temel müşteri panosu',
            ],
        ],

        'pro' => [
            'code' => 'pro',
            'name' => 'Pro',
            'price' => 499,
            'currency' => 'TRY',
            'interval' => 'month',
            // iyzico panelinde oluşturulan "pricing plan" referans kodu.
            'iyzico_plan_ref' => env('IYZICO_PRO_PLAN_REF'),
            'features' => [
                'max_stores' => 25,
                'live_prices' => true,    // anlık (Reverb)
                'branded_board' => true,
                'custom_domain' => true,
            ],
            'highlights' => [
                '25 mağazaya kadar',
                'Canlı fiyat (anlık)',
                'Markalı müşteri panosu',
                'Özel domain',
                'Öncelikli destek',
            ],
        ],

    ],

];
