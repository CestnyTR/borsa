# Nadirdoviz.com API Endpoint Bulma Rehberi

## Problem
Nadirdoviz.com sitesi **Blazor WebAssembly** kullanıyor. Bu, sayfanın tamamen JavaScript ile client-side render edildiği anlamına gelir. Bu yüzden normal HTML scraping çalışmıyor.

## Çözüm 1: API Endpoint'ini Bulma (ÖNERİLEN)

### Adımlar:

1. **Browser Developer Tools'u Açın**
   - Chrome/Edge: F12 veya Sağ tık → "İncele"
   - Firefox: F12 veya Sağ tık → "Elemanı Denetle"

2. **Network Tab'ına Geçin**
   - Developer Tools'da "Network" sekmesine tıklayın
   - "XHR" veya "Fetch/XHR" filtresini seçin

3. **Sayfayı Yenileyin**
   - F5 ile sayfayı yenileyin
   - https://www.nadirdoviz.com/fiyat-ekrani adresindeyken yapın

4. **API Çağrılarını Bulun**
   - Network tab'ında gelen isteklere bakın
   - Genellikle şu şekilde endpoint'ler olur:
     - `/api/rates`
     - `/api/prices`
     - `/api/canli`
     - `/api/data`
     - veya benzeri

5. **Endpoint'i Test Edin**
   - Bulunan endpoint'e sağ tıklayın
   - "Copy" → "Copy URL" seçin
   - Terminal'de test edin:
   ```bash
   curl -H "User-Agent: Mozilla/5.0" "https://www.nadirdoviz.com/API_ENDPOINT"
   ```

6. **JSON Yapısını İnceleyin**
   - Response'ta altın fiyatlarının hangi field'da olduğunu bulun
   - Örnek yapı:
   ```json
   {
     "rates": [
       {
         "name": "Altın/TL",
         "buy": 5807.88,
         "sell": 5824.51
       }
     ]
   }
   ```

7. **Scraper Kodunu Güncelleyin**
   - `app/Services/GoldScrapers/NadirDovizScraper.php` dosyasını açın
   - `$apiUrl` değişkenini bulduğunuz endpoint ile değiştirin
   - `fetchPrice()` metodundaki JSON parsing kısmını response yapısına göre güncelleyin

### Örnek Güncelleme:

```php
// NadirDovizScraper.php içinde

private string $apiUrl = 'https://www.nadirdoviz.com/BULUNAN_ENDPOINT';

public function fetchPrice(): array
{
    $response = Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0',
        'Accept' => 'application/json',
    ])->timeout(15)->get($this->apiUrl);

    if (!$response->successful()) {
        throw new \Exception('API request failed');
    }

    $data = $response->json();

    // JSON yapısına göre fiyatları çıkar
    // Örnek 1: Direkt rates array'i varsa
    foreach ($data['rates'] as $rate) {
        if ($rate['symbol'] === 'ALTIN_TL') {
            return [
                'buy' => (float) $rate['buy'],
                'sell' => (float) $rate['sell'],
            ];
        }
    }

    // Örnek 2: Direkt altın objesi varsa
    if (isset($data['gold'])) {
        return [
            'buy' => (float) $data['gold']['buy'],
            'sell' => (float) $data['gold']['sell'],
        ];
    }

    throw new \Exception('Gold price not found in API response');
}
```

## Çözüm 2: Alternatif Altın Fiyat Siteleri

Nadirdoviz.com API'si bulunmazsa veya kullanılmazsa, alternatif siteler:

### 1. Bigpara.com
- URL: `https://bigpara.hurriyet.com.tr/altin/`
- Avantaj: Statik HTML, kolay scraping
- API: `https://api.bigpara.hurriyet.com.tr/doviz/altin`

### 2. Doviz.com
- URL: `https://www.doviz.com/altin-fiyatlari`
- Avantaj: Popüler, güvenilir
- Scraping ile çalışabilir

### 3. Altın Fiyatları (Merkez Bankası)
- URL: `https://www.tcmb.gov.tr/`
- Avantaj: Resmi kaynak
- Dezavantaj: XML formatı, daha karmaşık

### Bigpara API Kullanım Örneği:

```php
<?php

namespace App\Services\GoldScrapers;

use Illuminate\Support\Facades\Http;

class BigparaScraper implements GoldScraperInterface
{
    public function getSourceCode(): string
    {
        return 'BIGPARA';
    }

    public function fetchPrice(): array
    {
        $response = Http::timeout(15)
            ->get('https://api.bigpara.hurriyet.com.tr/doviz/altin');

        if (!$response->successful()) {
            throw new \Exception('Bigpara API failed');
        }

        $data = $response->json();

        // Gram altın fiyatını bul
        foreach ($data as $item) {
            if (isset($item['SEMBOL']) && $item['SEMBOL'] === 'gram-altin') {
                return [
                    'buy' => (float) $item['ALIS'],
                    'sell' => (float) $item['SATIS'],
                ];
            }
        }

        throw new \Exception('Gold price not found');
    }
}
```

## Çözüm 3: Selenium/Puppeteer (Gelişmiş)

Eğer API bulunamazsa, JavaScript render etmek için headless browser kullanılabilir.

### Gereksinimler:
```bash
composer require php-webdriver/webdriver
# veya
npm install puppeteer
```

### Selenium Kurulumu:
1. ChromeDriver indir: https://chromedriver.chromium.org/
2. PATH'e ekle
3. Kod örneği:

```php
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;

$driver = RemoteWebDriver::create('http://localhost:4444',
    DesiredCapabilities::chrome());

$driver->get('https://www.nadirdoviz.com/fiyat-ekrani');
sleep(3); // JavaScript yüklenmesini bekle

$sellPrice = $driver->findElement(
    WebDriverBy::xpath('/html/body/div[2]/main/div[3]/div[2]/div[1]/div/div[2]/div[1]/div[2]/div/span[2]')
)->getText();

$driver->quit();
```

## Şu Anki Durum

- ✅ NadirDovizScraper sınıfı oluşturuldu
- ✅ GoldPriceAggregator'a eklendi
- ✅ Database'e NADIR_DOVIZ source eklendi
- ❌ API endpoint henüz bulunamadı
- ⚠️ Scraper şu an çalışmıyor (JavaScript rendering gerekiyor)

## Hızlı Test

API endpoint'ini bulduktan sonra test edin:

```bash
# Artisan command ile
php artisan gold:fetch

# Veya tinker ile
php artisan tinker
>>> (new \App\Services\GoldScrapers\NadirDovizScraper())->fetchPrice();
```

## Yardım

Eğer API endpoint'ini bulamıyorsanız:
1. Screenshot gönderin (Network tab'dan)
2. JSON response'u paylaşın
3. Alternatif site kullanımını düşünün

---

**NOT:** Mevcut sistem diğer 3 scraper ile çalışmaya devam ediyor. Nadirdoviz eklenirse 4 kaynaktan ortalama alınacak.
