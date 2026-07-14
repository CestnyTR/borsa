# Database Kullanım Örnekleri

## 1. Yeni Şirket Ekleme

```php
// Tinker'da:
$company = \App\Models\Company::create([
    'name' => 'Kuyumcu C',
    'code' => 'KUYUMCU_C',
    'is_active' => true,
]);

// Çarpanlarını ekle
$asset = \App\Models\GoldAsset::where('code', 'GRAM_GOLD_TRY')->first();

// 0.5 gram - %3 kar
\App\Models\CompanyGoldSetting::create([
    'company_id' => $company->id,
    'asset_id' => $asset->id,
    'weight_gram' => 0.5,
    'markup_percent' => 3.0,
    'markup_fixed' => 0,
    'rounding_step' => 0.25,
    'is_active' => true,
    'sort_order' => 1,
]);

// 2.5 gram - 10 TL sabit kar
\App\Models\CompanyGoldSetting::create([
    'company_id' => $company->id,
    'asset_id' => $asset->id,
    'weight_gram' => 2.5,
    'markup_percent' => 0,
    'markup_fixed' => 10.0,
    'rounding_step' => 1.0,
    'is_active' => true,
    'sort_order' => 2,
]);
```

## 2. Mevcut Çarpanları Güncelleme

```php
// Kuyumcu A'nın %2 kar marjını %2.5'e çıkar
\App\Models\CompanyGoldSetting::where('company_id', 1)
    ->update(['markup_percent' => 2.5]);

// Sadece 1 gram için güncelle
\App\Models\CompanyGoldSetting::where('company_id', 1)
    ->where('weight_gram', 1.0)
    ->update(['markup_percent' => 3.0]);
```

## 3. Hesaplama Mantığı

### Basit Yüzde Kar:
```
Base Fiyat: 2500 TL/gram
Gram: 0.5g
Kar: %2

Hesap:
  2500 × 0.5 = 1250 TL
  1250 × 1.02 = 1275 TL
  Yuvarla (0.10) = 1275.00 TL
```

### Sabit TL Kar:
```
Base Fiyat: 2500 TL/gram
Gram: 1g
Kar: +5 TL

Hesap:
  2500 × 1 = 2500 TL
  2500 + 5 = 2505 TL
  Yuvarla (1.00) = 2505 TL
```

### Kombine (Hem % Hem Sabit):
```
Base Fiyat: 2500 TL/gram
Gram: 1g
Kar: %2 + 3 TL

Hesap:
  2500 × 1 = 2500 TL
  2500 × 1.02 = 2550 TL
  2550 + 3 = 2553 TL
  Yuvarla (0.50) = 2553.00 TL
```

## 4. API Kullanımı

### Tüm şirketlerin fiyatlarını çek:
```bash
curl http://localhost:8000/api/companies/prices
```

### Belirli şirketin fiyatları:
```bash
curl http://localhost:8000/api/companies/KUYUMCU_A/prices
```

### Cevap Formatı:
```json
{
  "success": true,
  "data": {
    "company": {
      "id": 1,
      "name": "Kuyumcu A",
      "code": "KUYUMCU_A"
    },
    "base_price": {
      "asset": "Gram Altın",
      "unit": "gram",
      "sell_price": 2500.4667
    },
    "prices": [
      {
        "weight_gram": 0.25,
        "raw_price": 637.62,
        "final_price": 637.60,
        "markup": {
          "percent": 2,
          "fixed": 0,
          "rounding_step": 0.1
        }
      }
    ]
  }
}
```

## 5. Seeder ile Toplu Ekleme

Dosya: `database/seeders/GoldSystemSeeder.php`

```php
// Çoklu şirket ekleme örneği:
$companies = [
    'Kuyumcu A' => [
        ['weight' => 0.25, 'percent' => 2, 'step' => 0.10],
        ['weight' => 0.50, 'percent' => 2, 'step' => 0.10],
        ['weight' => 1.00, 'percent' => 2, 'step' => 0.10],
    ],
    'Kuyumcu B' => [
        ['weight' => 1.00, 'fixed' => 5, 'step' => 1.00],
        ['weight' => 2.00, 'fixed' => 8, 'step' => 1.00],
    ],
];

foreach ($companies as $name => $settings) {
    $company = Company::create(['name' => $name, ...]);

    foreach ($settings as $setting) {
        CompanyGoldSetting::create([...]);
    }
}
```

## 6. Admin Panel İçin CRUD İşlemleri

### Şirket Listesi:
```php
Route::get('/admin/companies', function() {
    return Company::with('goldSettings')->get();
});
```

### Çarpan Güncelleme:
```php
Route::put('/admin/settings/{id}', function($id) {
    $setting = CompanyGoldSetting::findOrFail($id);
    $setting->update(request()->only([
        'weight_gram',
        'markup_percent',
        'markup_fixed',
        'rounding_step'
    ]));
    return $setting;
});
```

## 7. Hesaplama Service'i

Dosya: `app/Services/CompanyGoldPriceService.php`

```php
private function calculatePrice(float $basePrice, CompanyGoldSetting $setting): array
{
    // 1. Gram ile çarp
    $base = $basePrice * (float) $setting->weight_gram;

    // 2. Yüzde kar ekle
    if ($setting->markup_percent > 0) {
        $base += $base * ((float) $setting->markup_percent / 100);
    }

    // 3. Sabit kar ekle
    if ($setting->markup_fixed > 0) {
        $base += (float) $setting->markup_fixed;
    }

    // 4. Yuvarla
    $finalPrice = $this->roundToStep($base, (float) $setting->rounding_step);

    return [
        'weight_gram' => (float) $setting->weight_gram,
        'raw_price' => round($base, 2),
        'final_price' => $finalPrice,
        'markup' => [
            'percent' => (float) $setting->markup_percent,
            'fixed' => (float) $setting->markup_fixed,
            'rounding_step' => (float) $setting->rounding_step,
        ],
    ];
}
```
