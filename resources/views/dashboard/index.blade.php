@extends('layouts.app')

@section('title', ($selectedStore->name ?? 'Fiyat Paneli'))

@section('content')
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-ink-900">{{ $selectedStore->name ?? 'Fiyat Paneli' }}</h1>
                <span id="livePill"
                      class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-ink-500">
                    <span id="liveDot" class="h-2 w-2 rounded-full bg-slate-400"></span>
                    <span id="liveText">Bağlanıyor…</span>
                </span>
            </div>
            <p class="mt-1 text-sm text-ink-500">
                Güncel altın alış &amp; satış fiyatları.
                <span id="updatedAgo" class="text-ink-400"></span>
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($selectedStore && $selectedStore->slug)
                <a href="{{ route('board', $selectedStore) }}" target="_blank"
                   class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-ink-700 hover:bg-slate-50">
                    📺 Müşteri Panosu
                </a>
            @endif
            @can('admin')
                <form method="POST" action="{{ route('dashboard.fetchNow') }}">
                    @csrf
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Verileri Yenile
                    </button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Kontroller: mağaza + fiyat kaynağı --}}
    <div class="mt-5 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-ink-500">Mağaza</span>
            <select id="storeSelect" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" {{ $isAdmin ? '' : 'disabled' }}>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ $selectedStore && $selectedStore->id === $store->id ? 'selected' : '' }}>
                        {{ $store->name }}{{ $store->is_active ? '' : ' (pasif)' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-ink-500">Fiyat kaynağı</span>
            <select id="sourceSelect" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></select>
        </label>

        @can('admin')
            @if($selectedStore)
                <a href="{{ route('admin.stores.multipliers.edit', $selectedStore) }}"
                   class="ml-auto text-sm font-medium text-brand hover:underline" style="color: var(--brand)">
                    Fiyat çarpanlarını düzenle →
                </a>
            @endif
        @endcan
    </div>

    {{-- ÜRÜN KARTLARI --}}
    <div id="productGrid" class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>

    {{-- Detaylı (teknik) veriler — varsayılan gizli --}}
    <details class="mt-8 rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-ink-700">
            Detaylı veriler (teknik)
        </summary>
        <div class="border-t border-slate-200 p-4">
            <h3 class="text-sm font-semibold text-ink-700">Ham piyasa verileri (HAS / ONS)</h3>
            <div class="mt-2 overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-ink-500">
                            <th class="py-2 pr-3">Kaynak</th>
                            <th class="py-2 pr-3">Enstrüman</th>
                            <th class="py-2 pr-3">Alış</th>
                            <th class="py-2 pr-3">Satış</th>
                            <th class="py-2 pr-3">Para</th>
                            <th class="py-2 pr-3">Zaman</th>
                        </tr>
                    </thead>
                    <tbody id="rawTableBody"></tbody>
                </table>
            </div>
        </div>
    </details>
@endsection

@push('scripts')
<script>
(function () {
    const initial = @json($initial);
    let state = initial;
    let currentSource = null;
    let timer = null, inflight = null;
    const prev = {}; // yön hesabı için önceki değerler

    const storeSelect = document.getElementById('storeSelect');
    const sourceSelect = document.getElementById('sourceSelect');
    const grid = document.getElementById('productGrid');
    const rawBody = document.getElementById('rawTableBody');
    const liveDot = document.getElementById('liveDot');
    const liveText = document.getElementById('liveText');
    const livePill = document.getElementById('livePill');
    const updatedAgo = document.getElementById('updatedAgo');

    const nf = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (v) => (v === null || v === undefined) ? '—' : nf.format(Number(v)) + ' ₺';

    // Kaynak isimleri (kullanıcı dostu)
    const sourceLabel = (code) => {
        const s = (state.sources || []).find(x => x.code === code);
        return s ? (s.name || s.code) : code;
    };

    function sourcesWithPrices() {
        const sp = state.store_prices || {};
        return (state.sources || []).map(s => s.code).filter(c => sp[c] && sp[c].products);
    }

    function pickSource() {
        const avail = sourcesWithPrices();
        if (currentSource && avail.includes(currentSource)) return currentSource;
        if (avail.includes('HAREM')) return 'HAREM';
        return avail[0] || (state.sources && state.sources[0] && state.sources[0].code) || null;
    }

    function buildSourceOptions() {
        const avail = sourcesWithPrices();
        const list = avail.length ? avail : (state.sources || []).map(s => s.code);
        sourceSelect.innerHTML = list.map(c =>
            `<option value="${c}">${sourceLabel(c)}${c === 'HAREM' ? ' · canlı' : ''}</option>`
        ).join('');
        if (!currentSource || !list.includes(currentSource)) currentSource = pickSource();
        if (currentSource) sourceSelect.value = currentSource;
    }

    function arrow(key, val) {
        if (val === null || val === undefined) return '';
        const before = prev[key];
        prev[key] = val;
        if (before === undefined || before === val) return '';
        return before < val
            ? '<span class="text-emerald-600">▲</span>'
            : '<span class="text-rose-600">▼</span>';
    }

    function timeAgo(iso) {
        if (!iso) return '';
        const t = Date.parse(iso);
        if (isNaN(t)) return '';
        const sec = Math.max(0, Math.round((Date.now() - t) / 1000));
        if (sec < 10) return 'az önce güncellendi';
        if (sec < 60) return sec + ' sn önce güncellendi';
        const min = Math.round(sec / 60);
        if (min < 60) return min + ' dk önce güncellendi';
        const h = Math.round(min / 60);
        return h + ' saat önce güncellendi';
    }

    function render() {
        buildSourceOptions();
        const src = currentSource;
        const payload = (state.store_prices || {})[src] || null;
        const products = state.products || [];

        grid.innerHTML = products.map(p => {
            const cell = payload && payload.products ? payload.products[p.code] : null;
            const bid = cell ? cell.bid : null;
            const ask = cell ? cell.ask : null;
            const aB = arrow(`${src}:${p.code}:b`, bid);
            const aA = arrow(`${src}:${p.code}:a`, ask);
            const initialCh = (p.name || '?').trim().charAt(0);
            return `
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white"
                              style="background: var(--brand, #C9A227)">${initialCh}</span>
                        <div class="font-semibold text-ink-900 leading-tight">${p.name}</div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-xs font-medium text-ink-500">Alış</div>
                            <div class="tnum text-lg font-bold text-ink-900">${money(bid)} ${aB}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-ink-500">Satış</div>
                            <div class="tnum text-lg font-bold text-ink-900">${money(ask)} ${aA}</div>
                        </div>
                    </div>
                </div>`;
        }).join('');

        // Ham veri tablosu (detay)
        rawBody.innerHTML = (state.sources || []).map(s =>
            ['HAS', 'ONS'].map(instr => {
                const row = (((state.market || {})[s.code] || {}).instruments || {})[instr] || null;
                const label = row ? (row.source_instrument || instr) : instr;
                const stale = row && row.stale
                    ? '<span class="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700">gecikmeli</span>' : '';
                return `<tr class="border-t border-slate-100">
                    <td class="py-2 pr-3 font-semibold">${sourceLabel(s.code)}</td>
                    <td class="py-2 pr-3">${label}</td>
                    <td class="py-2 pr-3 tnum">${row ? money(row.bid) : '—'}</td>
                    <td class="py-2 pr-3 tnum">${row ? money(row.ask) : '—'}</td>
                    <td class="py-2 pr-3">${row ? (row.currency || '—') : '—'}</td>
                    <td class="py-2 pr-3 text-ink-500">${row ? (row.fetched_at || '—') : '—'}${stale}</td>
                </tr>`;
            }).join('')
        ).join('');

        // "x dk önce" — kaynağın hesap zamanına göre
        updatedAgo.textContent = payload ? timeAgo(payload.calculated_at || state.generated_at) : '';
    }

    function setLive(on) {
        if (on) {
            liveDot.className = 'h-2 w-2 rounded-full bg-emerald-500 animate-pulseDot';
            liveText.textContent = 'Canlı';
            livePill.className = 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700';
        } else {
            liveDot.className = 'h-2 w-2 rounded-full bg-amber-400';
            liveText.textContent = 'Gecikmeli';
            livePill.className = 'inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700';
        }
    }

    async function fetchData() {
        if (inflight) inflight.abort();
        inflight = new AbortController();
        const storeId = storeSelect ? storeSelect.value : null;
        const url = new URL(@json(route('dashboard.data')), window.location.origin);
        if (storeId) url.searchParams.set('store_id', storeId);
        try {
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }, signal: inflight.signal });
            if (!res.ok) throw new Error('fail');
            const json = await res.json();
            if (json && json.data) { state = json.data; render(); }
        } catch (e) { /* stale-while-revalidate */ }
    }

    function stopPolling() { if (timer) clearInterval(timer); timer = null; }
    function startPolling() { stopPolling(); setLive(false); timer = setInterval(fetchData, 15000); }

    async function setupRealtime() {
        const reverbKey = @json(config('broadcasting.connections.reverb.key'));
        const reverbHost = @json(config('broadcasting.connections.reverb.options.host'));
        const reverbPort = Number(@json(config('broadcasting.connections.reverb.options.port')) || 8080);
        const reverbScheme = @json(config('broadcasting.connections.reverb.options.scheme')) || 'http';
        const forceTLS = reverbScheme === 'https';
        const authEndpoint = @json(url('/broadcasting/auth'));
        const storeId = storeSelect ? storeSelect.value : null;

        // Reverb gelene kadar polling ile veri akmaya devam etsin.
        startPolling();
        if (!reverbKey || !storeId) return;
        if (!window.Pusher) {
            await new Promise((resolve) => {
                const sc = document.createElement('script');
                sc.src = 'https://js.pusher.com/8.4.0/pusher.min.js';
                sc.onload = resolve; sc.onerror = resolve; document.head.appendChild(sc);
            });
        }
        if (!window.Pusher) return;

        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const pusher = new window.Pusher(reverbKey, {
            wsHost: reverbHost, wsPort: reverbPort, wssPort: reverbPort,
            forceTLS, enabledTransports: forceTLS ? ['wss'] : ['ws'], cluster: '',
            authEndpoint, auth: { headers: { 'X-CSRF-TOKEN': csrf } },
        });
        pusher.connection.bind('connected', () => { stopPolling(); setLive(true); });
        ['disconnected', 'unavailable', 'failed', 'error'].forEach(ev =>
            pusher.connection.bind(ev, () => { if (!timer) startPolling(); }));

        const ch = pusher.subscribe(`private-store.${storeId}`);
        ch.bind('store.prices.updated', fetchData);
        ch.bind('market.quotes.updated', fetchData);
    }

    if (storeSelect) {
        storeSelect.addEventListener('change', () => {
            const u = new URL(window.location.href);
            u.searchParams.set('store_id', storeSelect.value);
            window.location.href = u.toString();
        });
    }
    sourceSelect.addEventListener('change', () => { currentSource = sourceSelect.value; render(); });

    render();
    setupRealtime();
    // "x dk önce" etiketini canlı tut
    setInterval(() => {
        const payload = (state.store_prices || {})[currentSource] || null;
        updatedAgo.textContent = payload ? timeAgo(payload.calculated_at || state.generated_at) : '';
    }, 10000);
})();
</script>
@endpush
