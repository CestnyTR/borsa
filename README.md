# Borsa — Gerçek Zamanlı Altın & Piyasa Fiyat Platformu

Ham piyasa verilerini (HAS / ONS) birden çok kaynaktan toplayıp mağaza
çarpanlarıyla ürün fiyatlarına dönüştüren ve bunları **canlı** olarak panoya
yayınlayan Laravel uygulaması.

- **Framework:** Laravel 12 · PHP 8.4
- **Gerçek zamanlı:** Laravel Reverb (self-hosted, Pusher-uyumlu WebSocket)
- **Kaynaklar:** Ahlatcı (HTML scrape) · Nadir (API) · **Harem (Socket.IO, canlı)**

## Mimari

```
Kaynaklar
  Ahlatcı (HTML) ─┐
  Nadir (API)    ─┼─► MarketQuoteService ─► market_quotes (DB) + Cache
  Harem (Socket.IO, canlı) ─┘                    │
        │ harem:listen daemon                     ▼
        │                              StorePricingService ─► store_prices (DB) + Cache
        │                                         │  MarketQuotesUpdated / StorePricesUpdated
        ▼                                         ▼
  (cron: market:update, HAREM hariç)      queue worker ─► Reverb ─► tarayıcı
                                                   (private-store.{id} → /dashboard/data)
```

İki fiyat hattı vardır:
1. **Mağaza/ürün fiyatları:** `market_quotes` + `store_prices` (dakikalık cron + HAREM canlı akış).
2. **Şirket bazlı gram altın:** `gold_*` tabloları (15 dakikalık scrape + ortalama).

Detaylı analiz için [docs/ANALYSIS.md](docs/ANALYSIS.md), realtime tasarımı için
[docs/REALTIME.md](docs/REALTIME.md).

## HAREM canlı akış

HAREM eski HTTP uçlarını (`/tmp/altin.json`) kaldırıp Socket.IO'ya geçti
(`wss://hrmsocketonly.haremaltin.com`, `price_changed`). `harem:listen` daemon'ı
tek bir kalıcı bağlantı tutar, her tick'te fiyatları DB/cache'e yazar, mağaza
fiyatlarını yeniden hesaplar ve yayınlar. Cron poll bu kaynağı atlar
(`MARKET_STREAMED_SOURCES`) — çift bağlantı ve HAREM 429 riski önlenir.

## Kurulum

```bash
composer install
npm install            # varlıklar için (frontend pusher-js CDN'den yüklenir)
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan reverb:install   # REVERB_* zaten .env'de; gerekirse yeniden üretir
```

`.env` içinde önemli ayarlar:

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

MARKET_STREAMED_SOURCES=HAREM
MARKET_STREAM_DEBOUNCE_MS=1000

REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"   # prod: genel alan adı (nginx wss proxy)
REVERB_PORT=8080
REVERB_SCHEME=http        # prod: https
```

## Çalıştırma — PM2 (tek komut)

Tüm arka plan süreçleri (reverb, queue, cron/scheduler, harem) **tek komutla**
PM2 ile çalışır. Config: [ecosystem.config.cjs](ecosystem.config.cjs).

```bash
npm install -g pm2                    # bir kez
pm2 start ecosystem.config.cjs        # hepsini başlat
pm2 status                            # durum
pm2 logs                              # loglar (tek süreç: pm2 logs harem)
pm2 startup && pm2 save               # sunucu açılışında otomatik başlat
```

| Süreç (PM2 adı) | Komut | Görev |
|---|---|---|
| `reverb` | `reverb:start` | Event'leri tarayıcılara dağıtan websocket santralı |
| `queue` | `queue:work` | Broadcast job'larını işler |
| `cron` | `schedule:work` | `market:update` (dakikalık) + `gold:fetch` (15 dk) — ayrı crontab gerekmez |
| `harem` | `harem:listen` | HAREM'den canlı tick çeker |

- **Sunucuda web'i nginx + php-fpm sunar** (varsayılan; PM2 web'i çalıştırmaz).
- **Yerelde** dahili web sunucusunu da istersen: `APP_SERVE=1 pm2 start ecosystem.config.cjs`.
- Alternatif: Supervisor örneği [deploy/supervisor/borsa.conf](deploy/supervisor/borsa.conf).

**Prod notu:** Tarayıcı `localhost:8080`'e erişemez. `REVERB_HOST`'u genel alan
adına, `REVERB_SCHEME`'i `https`'e ayarla ve nginx ile `wss://alanadi → localhost:8080`
proxy'le; Reverb `0.0.0.0:8080` dinler.

## Dökümanlar

- [docs/REALTIME.md](docs/REALTIME.md) — REST → WebSocket dönüşümü, kanallar, HAREM köprüsü
- [docs/ANALYSIS.md](docs/ANALYSIS.md) — sistem/mimari/güvenlik analizi
- [docs/KURULUM.md](docs/KURULUM.md) — otomatik fiyat çekme kurulumu
- [docs/NADIRDOVIZ_API_SETUP.md](docs/NADIRDOVIZ_API_SETUP.md) — Nadirdöviz API keşfi
- [docs/ORNEKLER.md](docs/ORNEKLER.md) — veritabanı kullanım örnekleri
