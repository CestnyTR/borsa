# REST -> WebSocket Donusumu (Borsa)

## Kapsam
- Data producer ayni: cron + job pipeline DB'ye yazmaya devam eder.
- Delivery layer ek: Laravel Broadcasting + pusher-uyumlu servis.
- REST endpoint'ler fallback ve admin CRUD icin korunur.

## Broadcasting Setup
- Driver: `pusher`
- Config: `config/broadcasting.php`
- Env: `BROADCAST_DRIVER`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`
- Auth endpoint: `POST /broadcasting/auth` (Sanctum korumali)

## Sanctum Auth Setup
- Paket: `laravel/sanctum`
- `auth` guard: `api` -> `sanctum`
- API route'lari `auth:sanctum` ile korunur.
- Token endpoint: `POST /api/auth/token`

## Channel Authorization
- `private-store.{storeId}` -> user.store_id == storeId veya admin
- `private-company.{companyId}` -> user.company_id == companyId veya admin
- `private-admin.dashboard` -> admin

## Event Payload Kontrati
- Ortak zarf: `{ type, version, data, meta }`
- Meta alanlari: `emitted_at`, `trace_id`, `last_update_at`, `source_ids`
- Event'ler:
  - `market.quotes.updated`
  - `store.prices.updated`
  - `gold.prices.updated`
  - `company.gold.updated`
  - `system.heartbeat`

## Service Integration (Broadcast Hooks)
- `MarketQuoteService` -> `MarketQuotesUpdated` (admin kanali)
- `StorePricingService` -> `StorePricesUpdated` + `MarketQuotesUpdated` (store kanali)
- `GoldPriceAggregator` -> `GoldPricesUpdated` + `CompanyGoldUpdated`
- `MarketUpdateCommand` -> `SystemHeartbeat`

## REST Fallback Stratejisi
- WebSocket kopuksa UI belirli aralikla REST'den "latest" alir.
- REST cevap standardi: `{ success, data, meta, errors }`
- `meta.last_update_at` UI tarafinda stale hesaplamada kullanilir.

## Frontend Subscription (Ozet)
1. Kullanici girisi (session veya token):
   - Token: `POST /api/auth/token`
2. Pusher baglantisi:
   - `private-store.{storeId}`
   - `private-company.{companyId}`
   - `private-admin.dashboard` (admin)
3. Event dinleme:
   - `store.prices.updated`
   - `market.quotes.updated`
   - `company.gold.updated`
   - `gold.prices.updated`
4. Event geldiginde REST fallback ile cache yenile:
   - `/dashboard/data` veya `/api/v1/*/latest`
5. Stale hesapla:
   - `now - meta.last_update_at` > threshold

## Multi-Source Health & Fallback
- Tablo: `source_health` (domain + source_id bazli skor)
- Skor artisi: basarili cevapta +2
- Skor dususu: hata durumunda -10
- Selector: kaynagi skoruna gore siralar, her istekte deterministik sirayla dener.

## Migration Plan
- Phase 1: Sanctum + user-store/company baglari + channel auth
- Phase 2: Broadcasting event'leri + servis hook'lari
- Phase 3: Stale modeli + REST response standardizasyonu
- Phase 4: Source health + fallback stratejisi

## Risk Register
- WS auth acik kalirsa fiyatlar kamuya acilir
- Shared hosting WS server calistiramaz (cozum: managed pusher / self-hosted Reverb VDS'te)
- Cache/DB tutarsizligi (cozum: last_update_at modeli)
- Kaynak bagimliligi (cozum: health scoring + fallback)

## HAREM Canli Akis (Socket.IO Bridge)
HAREM eski HTTP uclarini (`/tmp/altin.json`, `/tmp/all_prices.json` -> 404) kaldirdi;
veri artik yalnizca Socket.IO ile geliyor: `wss://hrmsocketonly.haremaltin.com`,
event `price_changed`. Gram altin `ALTIN` key'inde (TRY), ons `ONS` key'inde (USD).

### Bilesenler
- `App\MarketData\Support\HaremSocketReader`
  - `fetchSnapshot()`: tek-seferlik okuma (cron/scraper fallback), 3sn paylasimli cache.
  - `listen($onTick, $shouldStop, $onIdle)`: kalici baglanti, her tick'te callback.
- `App\Console\Commands\HaremListenCommand` (`php artisan harem:listen`)
  - Kalici daemon; reconnect + backoff (1->10sn), SIGTERM ile temiz kapanis.
  - Her tick: `MarketQuoteService::ingestQuotes('HAREM', ...)` + `StorePricingService::computeAndStore()`.
  - Debounce: `config('market.stream_debounce_ms')` (varsayilan 1000ms) — Pusher/DB yukunu sinirlar.
- `config/market.php`
  - `streamed_sources` (`MARKET_STREAMED_SOURCES=HAREM`): cron poll bu kaynaklari ATLAR
    (daemon sahiplenir; cift baglanti -> HAREM 429 onlenir).

### Delivery (Reverb)
- Driver: `reverb` (self-hosted, VDS). Env: `REVERB_APP_ID/KEY/SECRET/HOST/PORT/SCHEME`.
- Broadcast event'leri `ShouldBroadcast` -> queue uzerinden gider; **queue worker sart**.
- Frontend pusher-js'i Reverb sunucusuna yonlendirir (bkz. dashboard `setupRealtime`).

### VDS'te calisan 3 kalici surec (supervisor: `deploy/supervisor/borsa.conf`)
1. `php artisan reverb:start`   — websocket santralı
2. `php artisan harem:listen`   — HAREM canli kaynak
3. `php artisan queue:work`     — broadcast job'lari

### Akis
HAREM socket --(harem:listen)--> DB/cache + computeAndStore --> MarketQuotesUpdated/StorePricesUpdated
  --> queue worker --> Reverb --> tarayici (private-store.{id}) --> `/dashboard/data` re-fetch --> render
