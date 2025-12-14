<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Altın Fiyatları Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div x-data="goldDashboard()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg">
            <div class="container mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-white">💰 Altın Fiyatları Dashboard</h1>
                        <p class="text-yellow-100 mt-2">Anlık ons altın fiyatları ve şirket karşılaştırmaları</p>
                        <div class="mt-2 space-x-4">
                            <a href="/admin/companies" class="inline-block text-yellow-100 hover:text-white text-sm underline">⚙️ Şirket Yönetimi</a>
                            <a href="/admin/prices/manual" class="inline-block text-yellow-100 hover:text-white text-sm underline">📝 Manuel Fiyat Girişi</a>
                        </div>
                    </div>
                    <div class="text-right">
                        <button
                            @click="toggleAutoRefresh()"
                            class="px-4 py-2 rounded-lg transition mb-2"
                            :class="autoRefreshEnabled ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-gray-400 hover:bg-gray-500 text-white'"
                        >
                            <span x-show="autoRefreshEnabled">✅ Otomatik Yenileme: Açık</span>
                            <span x-show="!autoRefreshEnabled">⏸️ Otomatik Yenileme: Kapalı</span>
                        </button>
                        <div x-show="autoRefreshEnabled" class="text-white text-sm">
                            ⏱️ Sonraki yenileme: <span class="font-bold" x-text="formatCountdown()"></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-8">
            <!-- Ons Gold Price Cards (3 Sources) -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">📊 Güncel Ons Altın Fiyatı</h2>
                    <button
                        @click="fetchCurrentPrice()"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition"
                        :disabled="loading"
                    >
                        <span x-show="!loading">🔄 Yenile</span>
                        <span x-show="loading">⏳ Yükleniyor...</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <template x-for="(source, index) in onsSources" :key="source.code">
                        <div class="bg-white rounded-lg shadow-md p-6 border-t-4"
                             :class="{
                                 'border-orange-500': index === 0,
                                 'border-purple-500': index === 1,
                                 'border-green-500': index === 2
                             }">
                            <!-- Kaynak Başlığı -->
                            <div class="mb-4">
                                <h3 class="text-lg font-bold"
                                    :class="{
                                        'text-orange-700': index === 0,
                                        'text-purple-700': index === 1,
                                        'text-green-700': index === 2
                                    }"
                                    x-text="source.name">
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">💡 1 Ons = 31.1 Gram</p>
                            </div>

                            <!-- Fiyatlar -->
                            <template x-if="!source.error">
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm text-gray-600 mb-1">Satış Fiyatı</p>
                                        <p class="text-3xl font-bold"
                                           :class="{
                                               'text-orange-700': index === 0,
                                               'text-purple-700': index === 1,
                                               'text-green-700': index === 2
                                           }"
                                           x-text="formatPrice(source.price.sell, source.currency || 'USD')">
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1" x-text="(source.currency || 'USD') + ' / ' + (source.unit || 'Ons')"></p>
                                    </div>

                                    <div>
                                        <p class="text-sm text-gray-600 mb-1">Alış Fiyatı</p>
                                        <p class="text-2xl font-semibold text-gray-700"
                                           x-text="source.price.buy ? formatPrice(source.price.buy, source.currency || 'USD') : '—'">
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1" x-text="(source.currency || 'USD') + ' / ' + (source.unit || 'Ons')"></p>
                                    </div>

                                    <div class="pt-3 border-t border-gray-200">
                                        <p class="text-xs text-gray-500">Canlı Veri</p>
                                        <p class="text-sm font-semibold text-gray-700" x-text="source.code"></p>
                                    </div>
                                </div>
                            </template>

                            <!-- Hata -->
                            <template x-if="source.error">
                                <div class="text-center py-4">
                                    <p class="text-sm text-red-600" x-text="source.error"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <template x-if="onsSources.length === 0 && !loading">
                    <div class="bg-white rounded-lg shadow-md p-6 text-center text-gray-500">
                        <p>Henüz ons fiyatı verisi yok.</p>
                    </div>
                </template>
            </div>

            <!-- Companies Prices -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">🏪 Şirket Fiyatları</h2>
                    <button
                        @click="fetchCompaniesPrices()"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition"
                        :disabled="loading"
                    >
                        <span x-show="!loading">🔄 Yenile</span>
                        <span x-show="loading">⏳ Yükleniyor...</span>
                    </button>
                </div>

                <template x-if="companies.length > 0">
                    <div class="space-y-6">
                        <template x-for="company in companies" :key="company.company.id">
                            <div class="border border-gray-200 rounded-lg p-6 hover:shadow-lg transition">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800" x-text="company.company.name"></h3>
                                        <p class="text-sm text-gray-500" x-text="'Kod: ' + company.company.code"></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">Baz Fiyat</p>
                                        <p class="text-lg font-semibold text-gray-700" x-text="formatPrice(company.base_price.sell_price)"></p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <template x-for="price in company.prices" :key="price.weight_gram">
                                        <div class="bg-gray-50 p-4 rounded-lg text-center hover:bg-gray-100 transition">
                                            <p class="text-sm text-gray-600 mb-1" x-text="price.weight_gram + ' Gram'"></p>
                                            <div class="mt-2 space-y-1">
                                                <template x-for="set in (company.price_sets || [])" :key="set.code">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span
                                                            :class="set.code === 'OFFICIAL' ? 'font-semibold text-blue-700' : 'text-gray-600'"
                                                            x-text="set.name"
                                                        ></span>
                                                        <span
                                                            :class="set.code === 'OFFICIAL' ? 'font-semibold text-blue-700' : 'text-gray-800'"
                                                            x-text="(() => { const p = getPriceForWeight(set.prices, price.weight_gram); return p ? formatPrice(p.final_price, set.currency || 'TRY') : '—'; })()"
                                                        ></span>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="mt-2 text-xs text-gray-500">
                                                <template x-if="price.markup.percent > 0">
                                                    <p>Kar: %<span x-text="price.markup.percent"></span></p>
                                                </template>
                                                <template x-if="price.markup.fixed > 0">
                                                    <p>Kar: +<span x-text="formatPrice(price.markup.fixed)"></span></p>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="companies.length === 0 && !loading">
                    <div class="text-center py-8 text-gray-500">
                        <p>Henüz şirket verisi yok. Lütfen yenile butonuna tıklayın.</p>
                    </div>
                </template>
            </div>

            <!-- Loading Overlay -->
            <template x-if="loading">
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-8 text-center">
                        <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-yellow-500 mx-auto mb-4"></div>
                        <p class="text-gray-700 font-semibold">Veriler yükleniyor...</p>
                    </div>
                </div>
            </template>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white mt-12 py-6">
            <div class="container mx-auto px-4 text-center">
                <p class="text-gray-400">💰 Altın Fiyatları Takip Sistemi - 2025</p>
                <p class="text-xs text-gray-500 mt-2">Veriler 3 farklı kaynaktan toplanmaktadır</p>
            </div>
        </footer>
    </div>

    <script>
        function goldDashboard() {
            return {
                loading: false,
                currentPrice: null,
                onsPrice: null,
                onsSources: [],
                companies: [],
                autoRefreshEnabled: true,
                autoRefreshInterval: null,
                nextRefreshIn: 900, // 15 dakika (saniye cinsinden)
                countdownInterval: null,

                async init() {
                    // İlk yükleme: Sadece mevcut verileri göster (scraping yok!)
                    await this.loadExistingData();
                    this.startAutoRefresh();
                    this.startCountdown();
                },

                async loadExistingData() {
                    // Scraping yapmadan sadece database'deki son veriyi getir
                    this.loading = true;
                    try {
                        const [priceResponse, onsResponse, companiesResponse] = await Promise.all([
                            fetch('/api/gold/current'),
                            fetch('/api/gold/ons'),
                            fetch('/api/companies/prices')
                        ]);

                        const priceData = await priceResponse.json();
                        const onsData = await onsResponse.json();
                        const companiesData = await companiesResponse.json();

                        if (priceData.success) {
                            this.currentPrice = priceData.data;
                        }

                        if (onsData.success) {
                            this.onsPrice = onsData.data;
                            this.onsSources = onsData.data.sources || [];
                        }

                        if (companiesData.success) {
                            this.companies = companiesData.data;
                        }
                    } catch (error) {
                        console.error('Veri yükleme hatası:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                startAutoRefresh() {
                    if (this.autoRefreshInterval) {
                        clearInterval(this.autoRefreshInterval);
                    }

                    if (this.autoRefreshEnabled) {
                        // Her 15 dakikada bir (900000ms) otomatik yenile
                        this.autoRefreshInterval = setInterval(async () => {
                            console.log('Otomatik yenileme başlatılıyor...');
                            await this.fetchDataOnly();
                            this.nextRefreshIn = 900; // Reset countdown
                        }, 900000); // 15 dakika
                    }
                },

                startCountdown() {
                    if (this.countdownInterval) {
                        clearInterval(this.countdownInterval);
                    }

                    this.countdownInterval = setInterval(() => {
                        if (this.autoRefreshEnabled && this.nextRefreshIn > 0) {
                            this.nextRefreshIn--;
                        }
                    }, 1000);
                },

                toggleAutoRefresh() {
                    this.autoRefreshEnabled = !this.autoRefreshEnabled;
                    if (this.autoRefreshEnabled) {
                        this.nextRefreshIn = 900;
                        this.startAutoRefresh();
                    } else {
                        if (this.autoRefreshInterval) {
                            clearInterval(this.autoRefreshInterval);
                        }
                    }
                },

                formatCountdown() {
                    const minutes = Math.floor(this.nextRefreshIn / 60);
                    const seconds = this.nextRefreshIn % 60;
                    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
                },

                async fetchDataOnly() {
                    // Sadece mevcut verileri çek (scraping yapmadan)
                    // NOT: Scraping cron job tarafından arka planda yapılıyor
                    try {
                        const [priceResponse, onsResponse, companiesResponse] = await Promise.all([
                            fetch('/api/gold/current'),
                            fetch('/api/gold/ons'),
                            fetch('/api/companies/prices')
                        ]);

                        const priceData = await priceResponse.json();
                        const onsData = await onsResponse.json();
                        const companiesData = await companiesResponse.json();

                        if (priceData.success) {
                            this.currentPrice = priceData.data;
                        }

                        if (onsData.success) {
                            this.onsPrice = onsData.data;
                            this.onsSources = onsData.data.sources || [];
                        }

                        if (companiesData.success) {
                            this.companies = companiesData.data;
                        }

                        console.log('Veriler güncellendi (otomatik, database\'den)');
                    } catch (error) {
                        console.error('Otomatik yenileme hatası:', error);
                    }
                },

                async triggerFetchFromSources() {
                    try {
                        const response = await fetch('/api/gold/fetch-now', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            console.log('Fiyatlar güncellendi:', data.data);
                            return true;
                        } else {
                            console.error('Fiyat güncelleme hatası:', data.message);
                            return false;
                        }
                    } catch (error) {
                        console.error('Fiyat çekme hatası:', error);
                        return false;
                    }
                },

                async fetchCurrentPrice() {
                    this.loading = true;
                    try {
                        // Önce kaynaklardan yeni fiyat çek
                        await this.triggerFetchFromSources();

                        // Sonra güncel fiyatı getir
                        const response = await fetch('/api/gold/current');
                        const data = await response.json();
                        if (data.success) {
                            this.currentPrice = data.data;
                        }

                        // Countdown'u sıfırla
                        this.nextRefreshIn = 900;
                    } catch (error) {
                        console.error('Error fetching current price:', error);
                        alert('Fiyat yüklenirken hata oluştu!');
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchCompaniesPrices() {
                    this.loading = true;
                    try {
                        // Önce kaynaklardan yeni fiyat çek
                        await this.triggerFetchFromSources();

                        // Sonra şirket fiyatlarını getir
                        const response = await fetch('/api/companies/prices');
                        const data = await response.json();
                        if (data.success) {
                            this.companies = data.data;
                        }

                        // Countdown'u sıfırla
                        this.nextRefreshIn = 900;
                    } catch (error) {
                        console.error('Error fetching companies prices:', error);
                        alert('Şirket fiyatları yüklenirken hata oluştu!');
                    } finally {
                        this.loading = false;
                    }
                },

                formatPrice(price, currency = 'TRY') {
                    return new Intl.NumberFormat('tr-TR', {
                        style: 'currency',
                        currency,
                        minimumFractionDigits: 2
                    }).format(price);
                },

                getPriceForWeight(prices, weightGram) {
                    if (!Array.isArray(prices)) return null;
                    return prices.find(p => Number(p.weight_gram) === Number(weightGram)) || null;
                },

                formatDateTime(dateString) {
                    return new Date(dateString).toLocaleString('tr-TR', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                timeAgo(dateString) {
                    const now = new Date();
                    const date = new Date(dateString);
                    const seconds = Math.floor((now - date) / 1000);

                    if (seconds < 60) return 'Az önce';
                    if (seconds < 3600) return Math.floor(seconds / 60) + ' dakika önce';
                    if (seconds < 86400) return Math.floor(seconds / 3600) + ' saat önce';
                    return Math.floor(seconds / 86400) + ' gün önce';
                }
            }
        }
    </script>
</body>
</html>
