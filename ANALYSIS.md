**Yonetici Ozeti**  
Proje iki paralel fiyat hattina sahip: (1) `gold_*` tablolarina dayali “sirket bazli gram altin” akisi ve (2) `market_quotes` + `store_prices` uzerinden “magaza/urun fiyatlari” akisi. Bu ayrim veri modeli ve cevap standartlarinda iki farkli hat olusturuyor. Market akisi cron + job zinciriyle (dakikalik, 30sn throttle) calisiyor; teslim katmanina broadcasting eklendi ve REST sadece fallback/historic icin kullaniliyor. Sirket fiyatlari tarafi 15 dakikada bir scrape + ortalama hesaplama kullaniyor ve yeni `last_*` timestamp alanlari ile stale hesaplamasi TTL’den ayrildi. API guvenligi Sanctum ile zorunlu hale getirildi; `/api` endpoint’leri artik token ile korunuyor. Multi-tenant izolasyonu user->store/company baglariyla getirildi; admin disi kullanicilar sadece kendi store/company verisini gorebilir. Event bazli push yapiya gecis yapildi (private channels) ve `source_health` ile kaynaklar icin deterministik fallback/health skoru eklendi.

**Sistem Mimarisi**  
- **Katmanlar**  
- Sunum: Blade, dashboard ve admin sayfalari. `resources/views/*`  
- Web/API: Controller’lar, middleware ve Sanctum. `app/Http/Controllers/*`, `app/Http/Middleware/PortalBasicAuth.php`, `config/sanctum.php`  
- Is Mantigi/Servis: fiyat hesaplama, market quote, scraping, health scoring. `app/Services/*`, `app/MarketData/*`, `app/Pricing/*`  
- Arkaplan Isleri: cron + queue jobs. `routes/console.php`, `app/Jobs/*`, `app/Console/Commands/*`  
- Veri: Eloquent modeller + DB. `app/Models/*`, `database/migrations/*`  
- Broadcasting: Pusher uyumlu servis, private channels. `config/broadcasting.php`, `routes/channels.php`, `app/Events/*`

**Veri Akis Diyagrami (Metin)**  
```
[External Sources]
  Ahlatci (HTML) / Nadir (API) / Harem (API)
        |
        |  Market quotes (HAS/ONS)
        v
MarketData Clients -> MarketQuoteService -> market_quotes (DB)
        |                            |
        |                            -> Cache market:latest:* (TTL 90s)
        v
StorePricingService -> store_prices (DB) + Cache store:prices:latest:* (TTL 90s)
        |
        v
Broadcast: private-store.{storeId} (market.quotes.updated, store.prices.updated)
Fallback REST: /api/v1/stores/{store}/prices/latest
```
```
[External Sources]
  Nadir (API) / Ahlatci (HTML) / Harem (API)
        |
        v
GoldPriceAggregator -> gold_raw_prices (DB)
        |
        v
aggregate -> gold_prices (DB)
        |
        v
CompanyGoldPriceService -> private-company.{companyId} (company.gold.updated)
Fallback REST: /api/companies/* ve /api/gold/*
```

**Veritabani Analizi**  
- **Tablolar**  
- `gold_assets`, `gold_sources`, `gold_raw_prices`, `gold_prices`, `companies`, `company_gold_settings`  
- `sources`, `market_quotes`, `stores`, `products`, `store_price_multipliers`, `store_prices`  
- `users`, `cache`, `jobs`, `source_health`, `personal_access_tokens`  
- **Iliskiler**  
- `company_gold_settings` -> `companies`, `gold_assets`  
- `gold_raw_prices` -> `gold_sources`, `gold_assets`  
- `gold_prices` -> `gold_assets`  
- `market_quotes` -> `sources`  
- `store_price_multipliers` -> `stores`, `sources`, `products`  
- `store_prices` -> `stores`, `sources`, `products`  
- `users` -> `stores`, `companies`  
- `source_health` -> `sources` (logical, fk tanimlanmamis)  
- **Pivot Kullanimi**  
- Ayrik pivot tablo yok; `store_price_multipliers` ve `company_gold_settings` bagimsiz domain tablolari.  
- **Normalization Riskleri**  
- “Source” kavrami iki yerde ayri tablolarla tekrar ediliyor (`gold_sources` vs `sources`), bu hem kaynak referanslarini hem de entegrasyonlarini ciftlestiriyor. `database/migrations/2025_12_04_094812_create_gold_sources_table.php`, `database/migrations/2025_12_18_000001_create_sources_table.php`.
- `source_health` tablosu `sources` ile mantiksal bagli; fk yok (explicit). `database/migrations/2025_12_18_000010_create_source_health_table.php`

**Is Mantigi Analizi**  
- **Price Rules**  
- Sirket fiyatlari: resmi gram fiyat * gram agirligi + yuzdelik ve/veya sabit markup + rounding. `app/Services/CompanyGoldPriceService.php`  
- Magaza fiyatlari: market quote (HAS) * store multiplier (BID/ASK). `app/Pricing/StorePricingService.php`  
- Health/Fallback: kaynaklar `source_health.score` ile siralanir, basarisizlar skor dusurur. `app/Services/SourceHealthService.php`, `app/MarketData/SourceSelector.php`  
- **Store Specific Multipliers**  
- `store_price_multipliers` tablosu ve admin arayuzu ile yonetim. `app/Http/Controllers/Admin/MultiplierPageController.php`, `app/Http/Controllers/Api/V1/Admin/MultiplierController.php`  
- **Admin Manipulation Noktalari**  
- Sirket ve setting CRUD. `app/Http/Controllers/Admin/CompanyController.php`, `app/Http/Controllers/Admin/GoldSettingController.php`  
- Store ve multipliers CRUD. `app/Http/Controllers/Admin/StoreController.php`, `app/Http/Controllers/Admin/MultiplierPageController.php`  
- Manuel tetikleme: dashboard “Veri Cek”. `app/Http/Controllers/DashboardController.php`

**API Contract Review**  
- **Response Standards**  
- Tum API’ler `{ success, data, meta, errors }` zarfina cekildi. `app/Http/Controllers/Controller.php`, `app/Http/Controllers/Api/*`  
- **Auth Requirements**  
- `/api/*`: `auth:sanctum` zorunlu, token endpoint’i `POST /api/auth/token`. `routes/api.php`, `app/Http/Controllers/Api/AuthTokenController.php`  
- `/api/v1/admin/*`: `auth:sanctum` + `can:admin`. `routes/api.php`  
- **Error Handling**  
- `GoldPriceController` catch ile `message` donduruyor; hatalar istemciye ham mesajla yansiyor.  
- `StorePriceController` source kodu gecersizse 404 (firstOrFail).  

**Performans Riskleri**  
- **Query Load**  
- `StorePricingService` her store x quote x multiplier icin `updateOrCreate` calistiriyor; yüksek N+1 riski. `app/Pricing/StorePricingService.php`  
- `MarketController` ve `DashboardController` DB fallback’lerinde `joinSub` + `groupBy` sorgulari var; data buyudugunde maliyet artar. `app/Http/Controllers/Api/V1/MarketController.php`, `app/Http/Controllers/DashboardController.php`  
- **Cache Invalidations**  
- Cache TTL 90s; stale tespiti artik `last_*` alanlariyla yapilacak sekilde tasarlandi. TTL/DB uyumsuzlugu riski devam eder. `app/Pricing/StorePricingService.php`, `database/migrations/2025_12_18_000009_add_last_update_timestamps.php`  
- **Cron Dependencies**  
- `market:update` dakikalik fakat 30sn throttle cache’e bagimli; cache silinirse beklenmedik calisma olabilir. `app/Console/Commands/MarketUpdateCommand.php`  
- `gold:fetch` 15 dakikada bir; kaynaklarin hepsi hata verirse resmi fiyat guncellenmiyor. `routes/console.php`, `app/Services/GoldPriceAggregator.php`

**Guvenlik Incelemesi**  
- **Auth Flows**  
- Web admin: session auth + Gate (`is_admin`). `app/Providers/AppServiceProvider.php`  
- API: Sanctum token auth zorunlu; `portal.basic` API disinda kullanilir. `app/Http/Middleware/PortalBasicAuth.php`, `routes/api.php`  
- **Tenant Escape Riskleri**  
- Kullanici store/company baglari ile scoping uygulanir; admin disi kullanici sadece kendi verisini gorur. `app/Models/User.php`, `app/Http/Controllers/*`  
- **Admin Abuse Vectors**  
- Admin multipliers ve company settings uzerinden fiyatlari tamamen kontrol edebilir; audit/log yok. `app/Http/Controllers/Admin/*`

**Uyarilar**  
- Store pricing sadece `HAS` enstrumanini isliyor, `ONS` quote’larina cache/DB yazilmiyor. `app/Pricing/StorePricingService.php`  
- Broadcasting icin managed provider gerekli; shared hosting kisiti nedeniyle self-hosted WS uygun degil. `config/broadcasting.php`

**Eksik veya Belirsiz Kisimlar (Sadece Liste)**  
- Token tabanli auth devrede; prod ortamda token lifecycle/policy net degil. `config/sanctum.php`  
- Tenant izolasyonu admin disi kullanicilar icin zorunlu; user atamasi olmadan erisim 403. (Isletim karari gerekli)  
- SEO/metadata (meta description/OG) bulunmuyor. `resources/views/layouts/app.blade.php`, `resources/views/welcome.blade.php`  
- Veri modelinde “source” kavrami iki farklı tabloyla ayrik; konsolidasyon gerekebilir. `database/migrations/*create_gold_sources_table.php`, `database/migrations/*create_sources_table.php`

**Iyilestirme Onerileri (Oncelik + Impact/Effort)**  
- P1: Token lifecycle/policy (expires, revoke, device name) netlestir. Impact: High, Effort: Medium. `config/sanctum.php`  
- P1: User provisioning surecinde store/company atamasi zorunlu kil (admin disi). Impact: High, Effort: Low.  
- P2: Store pricing’de `ONS` enstrumani desteğini netleştir (kullanilacaksa hesaplama ekle; değilse modelden çıkar). Impact: Medium, Effort: Low. `app/Pricing/StorePricingService.php`  
- P2: “sources” ve “gold_sources” duplicationsini tasarimda konsolide et veya net sinir çiz. Impact: Medium, Effort: High. `database/migrations/*`  
- P3: Admin degisiklikleri icin audit/log (settings ve multipliers). Impact: Medium, Effort: Medium. `app/Http/Controllers/Admin/*`

**Checklist (Kapsam)**  
- [x] Source code  
- [x] Migrations / Models / Seeders  
- [x] Routes / Controllers / Services  
- [x] Cache / Cron / Jobs  
- [x] API response pattern  
- [x] Security boundaries  
- [x] SEO & metadata  
