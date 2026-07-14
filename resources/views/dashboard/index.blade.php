@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $initialMarket = $initial['market'] ?? [];
    $initialStorePrices = $initial['store_prices'] ?? [];
    $sources = $initial['sources'] ?? [];
    $products = $initial['products'] ?? [];
@endphp

@section('content')
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Piyasa & Ürün Fiyatları</h1>
            <p class="text-sm text-gray-600 mt-1">
                Ham (HAS/ONS) ve mağaza çarpanlarıyla hesaplı 8 ürün fiyatı.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="text-right">
                <div class="text-xs text-gray-500">Son güncelleme</div>
                <div class="text-sm font-mono text-gray-700" id="generatedAt">{{ $initial['generated_at'] ?? '—' }}</div>
            </div>

            @can('admin')
                <form method="POST" action="{{ route('dashboard.fetchNow') }}">
                    @csrf
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm">
                        Veri Çek
                    </button>
                </form>
            @endcan

            <button id="refreshBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                Yenile
            </button>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-lg shadow-md p-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <div class="text-sm font-semibold text-gray-800">Mağaza</div>
                <div class="text-xs text-gray-500">Seçili mağazaya göre hesaplı fiyatlar gösterilir.</div>
            </div>

            <div class="flex items-center gap-3">
                <select id="storeSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" {{ $isAdmin ? '' : 'disabled' }}>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ $selectedStore && $selectedStore->id === $store->id ? 'selected' : '' }}>
                            {{ $store->name }}{{ $store->is_active ? '' : ' (pasif)' }}
                        </option>
                    @endforeach
                </select>

                @can('admin')
                    @if($selectedStore)
                        <a href="{{ route('admin.stores.multipliers.edit', $selectedStore) }}"
                           class="text-sm text-blue-700 hover:text-blue-900 underline">
                            Çarpanları düzenle
                        </a>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-lg shadow-md p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800">Ham Veriler (HAS / ONS)</h2>
                <div id="marketLoading" class="text-xs text-gray-500 hidden">Yükleniyor…</div>
            </div>

            <div class="mt-3 overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-gray-600">
                        <th class="py-2 pr-3">Kaynak</th>
                        <th class="py-2 pr-3">Enstrüman</th>
                        <th class="py-2 pr-3">Alış (BID)</th>
                        <th class="py-2 pr-3">Satış (ASK)</th>
                        <th class="py-2 pr-3">Para</th>
                        <th class="py-2 pr-3">Zaman</th>
                    </tr>
                    </thead>
                    <tbody id="marketTableBody">
                    @foreach($sources as $s)
                        @foreach(['HAS', 'ONS'] as $instrument)
                            @php
                                $row = $initialMarket[$s['code']]['instruments'][$instrument] ?? null;
                            @endphp
                            <tr class="border-t">
                                <td class="py-2 pr-3 font-semibold">{{ $s['code'] }}</td>
                                <td class="py-2 pr-3">{{ $row['source_instrument'] ?? $instrument }}</td>
                                <td class="py-2 pr-3 font-mono">{{ $row && $row['bid'] !== null ? number_format((float)$row['bid'], 4) : '—' }}</td>
                                <td class="py-2 pr-3 font-mono">{{ $row && $row['ask'] !== null ? number_format((float)$row['ask'], 4) : '—' }}</td>
                                <td class="py-2 pr-3">{{ $row['currency'] ?? '—' }}</td>
                                <td class="py-2 pr-3">
                                    <span class="font-mono">{{ $row['fetched_at'] ?? '—' }}</span>
                                    @if($row && ($row['stale'] ?? false))
                                        <span class="ml-2 text-xs text-orange-700 bg-orange-50 px-2 py-1 rounded">stale</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white rounded-lg shadow-md p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800">Hesaplı Ürün Fiyatları (8 Ürün)</h2>
                <div id="pricesLoading" class="text-xs text-gray-500 hidden">Yükleniyor…</div>
            </div>

            <div class="mt-3 overflow-auto">
                <div id="pricesTables">
                    @foreach($sources as $s)
                        @php
                            $payload = $initialStorePrices[$s['code']] ?? null;
                        @endphp
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-800">{{ $s['code'] }}</h3>
                                <div class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $payload['calculated_at'] ?? '—' }}</span>
                                    @if($payload && ($payload['stale'] ?? false))
                                        <span class="ml-2 text-xs text-orange-700 bg-orange-50 px-2 py-1 rounded">stale</span>
                                    @endif
                                </div>
                            </div>
                            <table class="min-w-full text-sm mt-2">
                                <thead>
                                <tr class="text-left text-gray-600">
                                    <th class="py-2 pr-3">Ürün</th>
                                    <th class="py-2 pr-3">Alış (BID)</th>
                                    <th class="py-2 pr-3">Satış (ASK)</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($products as $p)
                                    @php
                                        $cell = $payload['products'][$p['code']] ?? null;
                                    @endphp
                                    <tr class="border-t">
                                        <td class="py-2 pr-3">{{ $p['name'] }} <span class="text-xs text-gray-500">({{ $p['code'] }})</span></td>
                                        <td class="py-2 pr-3 font-mono">{{ $cell && $cell['bid'] !== null ? number_format((float)$cell['bid'], 4) : '—' }}</td>
                                        <td class="py-2 pr-3 font-mono">{{ $cell && $cell['ask'] !== null ? number_format((float)$cell['ask'], 4) : '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <div class="mt-2 text-xs text-gray-500">
        Otomatik güncelleme: 30sn. Veri 90sn’den eskiyse “stale” olarak işaretlenir.
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const initial = @json($initial);
            let state = initial;
            let timer = null;
            let inflight = null;

            const marketLoading = document.getElementById('marketLoading');
            const pricesLoading = document.getElementById('pricesLoading');
            const generatedAt = document.getElementById('generatedAt');
            const storeSelect = document.getElementById('storeSelect');
            const refreshBtn = document.getElementById('refreshBtn');

            const fmt = (v) => (v === null || v === undefined) ? '—' : Number(v).toLocaleString('tr-TR', {maximumFractionDigits: 4});

            function render() {
                generatedAt.textContent = state.generated_at || '—';

                const sources = state.sources || [];

                const marketBody = document.getElementById('marketTableBody');
                marketBody.innerHTML = sources.map(s => {
                    return ['HAS', 'ONS'].map(instr => {
                        const row = (((state.market || {})[s.code] || {}).instruments || {})[instr] || null;
                        const stale = row && row.stale ? `<span class="ml-2 text-xs text-orange-700 bg-orange-50 px-2 py-1 rounded">stale</span>` : '';
                        const label = row ? (row.source_instrument || row.instrument || instr) : instr;
                        return `
                            <tr class="border-t">
                                <td class="py-2 pr-3 font-semibold">${s.code}</td>
                                <td class="py-2 pr-3">${label}</td>
                                <td class="py-2 pr-3 font-mono">${row ? fmt(row.bid) : '—'}</td>
                                <td class="py-2 pr-3 font-mono">${row ? fmt(row.ask) : '—'}</td>
                                <td class="py-2 pr-3">${row ? (row.currency || '—') : '—'}</td>
                                <td class="py-2 pr-3"><span class="font-mono">${row ? (row.fetched_at || '—') : '—'}</span>${stale}</td>
                            </tr>
                        `;
                    }).join('');
                }).join('');

                const products = state.products || [];
                const pricesTables = document.getElementById('pricesTables');
                pricesTables.innerHTML = sources.map(s => {
                    const payload = (state.store_prices || {})[s.code] || null;
                    const stale = payload && payload.stale ? `<span class="ml-2 text-xs text-orange-700 bg-orange-50 px-2 py-1 rounded">stale</span>` : '';
                    const calculatedAt = payload ? (payload.calculated_at || '—') : '—';
                    const rows = products.map(p => {
                        const cell = payload && payload.products ? payload.products[p.code] : null;
                        return `
                            <tr class="border-t">
                                <td class="py-2 pr-3">${p.name} <span class="text-xs text-gray-500">(${p.code})</span></td>
                                <td class="py-2 pr-3 font-mono">${cell ? fmt(cell.bid) : '—'}</td>
                                <td class="py-2 pr-3 font-mono">${cell ? fmt(cell.ask) : '—'}</td>
                            </tr>
                        `;
                    }).join('');

                    return `
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-800">${s.code}</h3>
                                <div class="text-xs text-gray-500">
                                    <span class="font-mono">${calculatedAt}</span>${stale}
                                </div>
                            </div>
                            <table class="min-w-full text-sm mt-2">
                                <thead>
                                    <tr class="text-left text-gray-600">
                                        <th class="py-2 pr-3">Ürün</th>
                                        <th class="py-2 pr-3">Alış (BID)</th>
                                        <th class="py-2 pr-3">Satış (ASK)</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    `;
                }).join('');
            }

            async function fetchData({showLoading = true} = {}) {
                if (inflight) inflight.abort();
                inflight = new AbortController();

                const storeId = storeSelect ? storeSelect.value : null;
                const url = new URL(@json(route('dashboard.data')), window.location.origin);
                if (storeId) url.searchParams.set('store_id', storeId);

                try {
                    if (showLoading) {
                        marketLoading.classList.remove('hidden');
                        pricesLoading.classList.remove('hidden');
                    }
                    const res = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        signal: inflight.signal
                    });
                    if (!res.ok) throw new Error('Request failed');
                    const json = await res.json();
                    if (json && json.data) {
                        state = json.data;
                        render();
                    }
                } catch (e) {
                    // no-op (stale-while-revalidate)
                } finally {
                    marketLoading.classList.add('hidden');
                    pricesLoading.classList.add('hidden');
                }
            }

            function stopPolling() {
                if (timer) clearInterval(timer);
                timer = null;
            }

            function startPolling() {
                stopPolling();
                timer = setInterval(() => fetchData({showLoading: false}), 30000);
            }

            async function setupRealtime() {
                // Reverb konuşur Pusher protokolüyle, bu yüzden pusher-js'i
                // kendi Reverb sunucumuza yönlendiriyoruz (bulut Pusher'a değil).
                const reverbKey = @json(config('broadcasting.connections.reverb.key'));
                const reverbHost = @json(config('broadcasting.connections.reverb.options.host'));
                const reverbPort = Number(@json(config('broadcasting.connections.reverb.options.port')) || 8080);
                const reverbScheme = @json(config('broadcasting.connections.reverb.options.scheme')) || 'http';
                const forceTLS = reverbScheme === 'https';
                const authEndpoint = @json(url('/broadcasting/auth'));
                const storeId = storeSelect ? storeSelect.value : null;

                if (!reverbKey || !storeId) {
                    startPolling();
                    return;
                }

                if (!window.Pusher) {
                    await new Promise((resolve) => {
                        const script = document.createElement('script');
                        script.src = 'https://js.pusher.com/8.4.0/pusher.min.js';
                        script.onload = resolve;
                        script.onerror = resolve;
                        document.head.appendChild(script);
                    });
                }

                if (!window.Pusher) {
                    startPolling();
                    return;
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const pusher = new window.Pusher(reverbKey, {
                    wsHost: reverbHost,
                    wsPort: reverbPort,
                    wssPort: reverbPort,
                    forceTLS: forceTLS,
                    enabledTransports: ['ws', 'wss'],
                    cluster: '',
                    authEndpoint,
                    auth: {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        }
                    }
                });

                pusher.connection.bind('connected', () => stopPolling());
                pusher.connection.bind('disconnected', () => startPolling());
                pusher.connection.bind('error', () => startPolling());

                const channel = pusher.subscribe(`private-store.${storeId}`);
                channel.bind('store.prices.updated', () => fetchData({showLoading: false}));
                channel.bind('market.quotes.updated', () => fetchData({showLoading: false}));
            }

            if (storeSelect) {
                storeSelect.addEventListener('change', () => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('store_id', storeSelect.value);
                    window.location.href = url.toString();
                });
            }

            refreshBtn.addEventListener('click', () => fetchData({showLoading: true}));

            render();
            setupRealtime();
        })();
    </script>
@endpush
