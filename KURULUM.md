# 🎯 Altın Fiyatları Otomatik Takip Sistemi - Kurulum Tamamlandı!

## ✅ Çalışan Özellikler

### 1. **Otomatik Fiyat Çekme (Chrome Headless)**
- ✅ Nadirdoviz.com'dan gerçek fiyatlar otomatik çekiliyor
- ✅ Blazor/JavaScript render ediliyor
- ✅ Her 15 dakikada bir otomatik güncelleme (cron)
- ✅ Hiç manuel işlem yok!

### 2. **Dashboard**
- URL: http://127.0.0.1:8000/
- Gerçek zamanlı fiyat görüntüleme
- Şirket bazlı çarpan hesaplamaları
- 15 dakika otomatik yenileme

### 3. **Admin Paneli**
- Şirket yönetimi: http://127.0.0.1:8000/admin/companies
- Çarpan ayarları (inline editing)
- Manuel fiyat girişi (opsiyonel)

### 4. **API Endpoints**
- `GET /api/gold/current` - Güncel altın fiyatı
- `POST /api/gold/fetch-now` - Anında fiyat çek
- `GET /api/companies/prices` - Tüm şirket fiyatları
- `GET /api/companies/{code}/prices` - Belirli şirket

---

## 🤖 Cron Job Kurulumu (Otomatik Çalışma)

### Adım 1: Crontab Düzenle
```bash
crontab -e
```

### Adım 2: Bu Satırı Ekle
```bash
* * * * * cd /home/fahri/DevProjects/01_Laravel/borsa && php artisan schedule:run >> /dev/null 2>&1
```

### Adım 3: Kaydet ve Çık
- Vim: `:wq`
- Nano: `Ctrl+X` → `Y` → `Enter`

### Test Et
```bash
# Cron'un çalıştığını kontrol et
php artisan schedule:list

# Cron'u manuel test et
php artisan schedule:run
```

---

## 📊 Sistem Akışı

```
Her 15 Dakikada:
  ┌─────────────────────────────────────┐
  │  Laravel Schedule (Cron)            │
  └──────────────┬──────────────────────┘
                 │
                 ▼
  ┌─────────────────────────────────────┐
  │  php artisan gold:fetch             │
  └──────────────┬──────────────────────┘
                 │
                 ▼
  ┌─────────────────────────────────────┐
  │  Chrome Headless Başlat             │
  │  → Nadirdoviz.com'u Aç              │
  │  → Blazor/SignalR Yüklensin (10s)   │
  │  → HTML'i Al                         │
  │  → Fiyatları Parse Et                │
  └──────────────┬──────────────────────┘
                 │
                 ▼
  ┌─────────────────────────────────────┐
  │  Database'e Kaydet                  │
  │  → gold_raw_prices (ham veri)       │
  │  → gold_prices (aggregated)         │
  └──────────────┬──────────────────────┘
                 │
                 ▼
  ┌─────────────────────────────────────┐
  │  Şirket Çarpanları Uygula           │
  │  → Her şirket için hesapla          │
  │  → Kar marjı ekle                   │
  │  → Yuvarlama yap                    │
  └──────────────┬──────────────────────┘
                 │
                 ▼
  ┌─────────────────────────────────────┐
  │  Dashboard'da Göster                │
  │  → API: /api/companies/prices       │
  │  → Frontend: Alpine.js              │
  └─────────────────────────────────────┘
```

---

## 🔧 Geliştirici Komutları

### Fiyat Çekme
```bash
# Manuel fiyat çek
php artisan gold:fetch

# Log'ları izle
tail -f storage/logs/laravel.log | grep NadirDoviz

# Database'i kontrol et
php artisan tinker
>>> App\Models\GoldPrice::latest()->first();
>>> App\Models\GoldRawPrice::latest()->take(5)->get();
```

### Şirket Yönetimi
```bash
# Şirketleri listele
php artisan tinker
>>> App\Models\Company::with('goldSettings')->get();

# Yeni şirket ekle
>>> App\Models\Company::create(['name' => 'Test Kuyumcu', 'code' => 'TEST', 'is_active' => true]);
```

### API Test
```bash
# Güncel fiyat
curl http://127.0.0.1:8000/api/gold/current | jq

# Şirket fiyatları
curl http://127.0.0.1:8000/api/companies/prices | jq

# Anında fiyat çek (manuel trigger)
curl -X POST http://127.0.0.1:8000/api/gold/fetch-now | jq
```

---

## 🎨 Özelleştirme

### Çarpan Ayarları
1. Admin paneline git: `/admin/companies`
2. Şirketi seç
3. "Yeni Çarpan Ekle" butonuna tıkla
4. Parametreleri ayarla:
   - **Gram Ağırlığı**: 0.25, 0.5, 1, 5, 10 vb.
   - **% Kar**: 2.5 → %2.5 kar marjı
   - **Sabit Kar**: 10 → +10 TL
   - **Yuvarlama**: 0.10 → 10 kuruşa yuvarla

### Güncelleme Sıklığı
`routes/console.php` dosyasını düzenle:
```php
// Her 5 dakikada bir
Schedule::command('gold:fetch')->everyFiveMinutes();

// Her 30 dakikada bir
Schedule::command('gold:fetch')->everyThirtyMinutes();

// Her saat başı
Schedule::command('gold:fetch')->hourly();
```

### Timeout Ayarları
Chrome'un bekleme süresini ayarlamak için:
`app/Services/GoldScrapers/NadirDovizScraper.php`
```php
// Satır 49: Blazor yüklenmesi için bekleme
sleep(10); // 10 saniye (artırabilir/azaltabilirsiniz)
```

---

## 🐛 Sorun Giderme

### Chrome Hatası
```bash
# Chrome yüklü mü?
which google-chrome

# Chrome versiyonu
google-chrome --version

# Headless test
google-chrome --headless --dump-dom https://www.google.com
```

### Cron Çalışmıyor
```bash
# Cron log'larını kontrol et
grep CRON /var/log/syslog

# Laravel schedule log
php artisan schedule:work
```

### Fiyat Çekilmiyor
```bash
# Log'ları kontrol et
tail -100 storage/logs/laravel.log

# Manuel test
php artisan gold:fetch --verbose

# Chrome'u görselleştir (debug)
# NadirDovizScraper.php'de: 'headless' => false
```

### Database Hatası
```bash
# Migration'ları çalıştır
php artisan migrate

# Seeder'ları çalıştır
php artisan db:seed --class=GoldSystemSeeder
```

---

## 📦 Gereksinimler

### Sunucu
- PHP 8.2+
- SQLite veya MySQL
- Google Chrome / Chromium
- Composer

### PHP Extensions
- dom
- libxml
- mbstring
- sqlite3 / pdo_mysql

### Composer Packages
- laravel/framework: ^12.0
- chrome-php/chrome: ^1.14
- guzzlehttp/guzzle: ^7.0

---

## 🚀 Üretim (Production) Deployment

### 1. Environment Ayarları
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Supervisor (Opsiyonel - Queue için)
```ini
[program:borsa-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work
autostart=true
autorestart=true
numprocs=1
```

### 3. Nginx/Apache
Standart Laravel production setup.

---

## 📞 Destek

Herhangi bir sorun için:
1. Log dosyalarını kontrol et: `storage/logs/laravel.log`
2. Verbose mod ile test et: `php artisan gold:fetch --verbose`
3. Chrome'u görselleştirerek debug et

---

## 🎉 Sonuç

Sistem tamamen otomatik çalışıyor! Artık:
- ✅ Her 15 dakikada gerçek fiyatlar çekiliyor
- ✅ Şirket bazlı çarpanlar otomatik hesaplanıyor
- ✅ Dashboard'da anlık görüntüleniyor
- ✅ Hiçbir manuel işlem gerekmiyor!

**Manuel işlem istiyorsan**: `/admin/prices/manual` sayfasını kullanabilirsin.
**API ile entegrasyon**: REST API endpoint'leri hazır.
**Mobil uygulama**: API'ler üzerinden kolayca yapılabilir.

Başarılar! 🚀
