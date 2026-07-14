<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $store->name }} — Fiyat Panosu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
            keyframes: { pulseDot: { '0%,100%': { opacity: 1 }, '50%': { opacity: .3 } } },
            animation: { pulseDot: 'pulseDot 1.4s ease-in-out infinite' },
        } } }
    </script>
    <style>
        :root { --brand: {{ $brandColor }}; }
        html, body { height: 100%; }
        body { -webkit-font-smoothing: antialiased; }
        .tnum { font-variant-numeric: tabular-nums; }
        .flash-up { animation: fu .9s ease-out; }
        .flash-down { animation: fd .9s ease-out; }
        @keyframes fu { from { background: rgba(16,185,129,.22) } to { background: transparent } }
        @keyframes fd { from { background: rgba(244,63,94,.22) } to { background: transparent } }
    </style>
</head>
<body class="bg-slate-950 text-white font-sans min-h-screen flex flex-col">
    <header class="flex items-center justify-between px-6 sm:px-10 py-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            @if($store->logo_url)
                <img src="{{ $store->logo_url }}" alt="" class="h-10 w-auto">
            @else
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-950 text-xl font-black"
                      style="background: var(--brand)">₺</span>
            @endif
            <div>
                <div class="text-xl sm:text-2xl font-extrabold leading-tight">{{ $store->name }}</div>
                <div class="text-xs text-white/50">Güncel Altın Fiyatları</div>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div id="clock" class="tnum text-2xl sm:text-3xl font-bold text-white/90"></div>
            <span id="livePill" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold">
                <span id="liveDot" class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                <span id="liveText">Bağlanıyor…</span>
            </span>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6">
        <div id="grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 h-full auto-rows-fr"></div>
    </main>

    <footer class="px-6 sm:px-10 py-3 text-center text-xs text-white/40 border-t border-white/10">
        <span id="updatedAgo"></span> · Fiyatlar bilgilendirme amaçlıdır.
    </footer>

<script>
(function () {
    let state = @json($initial);
    const storeId = @json($store->id);
    let currentSource = null;
    const prev = {};
    let timer = null, inflight = null;

    const grid = document.getElementById('grid');
    const clock = document.getElementById('clock');
    const liveDot = document.getElementById('liveDot');
    const liveText = document.getElementById('liveText');
    const livePill = document.getElementById('livePill');
    const updatedAgo = document.getElementById('updatedAgo');

    const nf = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (v) => (v === null || v === undefined) ? '—' : nf.format(Number(v)) + ' ₺';

    function pickSource() {
        const sp = state.store_prices || {};
        const avail = (state.sources || []).map(s => s.code).filter(c => sp[c] && sp[c].products);
        if (avail.includes('HAREM')) return 'HAREM';
        return avail[0] || null;
    }

    function timeAgo(iso) {
        if (!iso) return '';
        const t = Date.parse(iso); if (isNaN(t)) return '';
        const sec = Math.max(0, Math.round((Date.now() - t) / 1000));
        if (sec < 10) return 'az önce güncellendi';
        if (sec < 60) return sec + ' sn önce güncellendi';
        const m = Math.round(sec / 60);
        if (m < 60) return m + ' dk önce güncellendi';
        return Math.round(m / 60) + ' saat önce güncellendi';
    }

    function trend(key, val) {
        if (val === null || val === undefined) return { arrow: '', cls: '' };
        const before = prev[key]; prev[key] = val;
        if (before === undefined || before === val) return { arrow: '', cls: '' };
        return before < val
            ? { arrow: '▲', cls: 'text-emerald-400', flash: 'flash-up' }
            : { arrow: '▼', cls: 'text-rose-400', flash: 'flash-down' };
    }

    function render() {
        currentSource = currentSource && (state.store_prices || {})[currentSource] ? currentSource : pickSource();
        const payload = (state.store_prices || {})[currentSource] || null;
        const products = state.products || [];

        grid.innerHTML = products.map(p => {
            const cell = payload && payload.products ? payload.products[p.code] : null;
            const bid = cell ? cell.bid : null, ask = cell ? cell.ask : null;
            const tB = trend(`${p.code}:b`, bid), tA = trend(`${p.code}:a`, ask);
            const flash = tA.flash || tB.flash || '';
            return `
                <div class="${flash} rounded-2xl border border-white/10 bg-white/5 p-5 flex flex-col justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: var(--brand)"></span>
                        <div class="text-lg sm:text-xl font-bold">${p.name}</div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs sm:text-sm font-medium text-white/50 uppercase tracking-wide">Alış</div>
                            <div class="tnum text-2xl sm:text-3xl font-extrabold ${tB.cls}">${money(bid)} <span class="text-lg">${tB.arrow}</span></div>
                        </div>
                        <div>
                            <div class="text-xs sm:text-sm font-medium text-white/50 uppercase tracking-wide">Satış</div>
                            <div class="tnum text-2xl sm:text-3xl font-extrabold ${tA.cls}">${money(ask)} <span class="text-lg">${tA.arrow}</span></div>
                        </div>
                    </div>
                </div>`;
        }).join('');

        updatedAgo.textContent = payload ? timeAgo(payload.calculated_at || state.generated_at) : '';
    }

    function setLive(on) {
        if (on) {
            liveDot.className = 'h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulseDot';
            liveText.textContent = 'Canlı';
            livePill.className = 'inline-flex items-center gap-2 rounded-full bg-emerald-500/15 text-emerald-300 px-3 py-1.5 text-sm font-semibold';
        } else {
            liveDot.className = 'h-2.5 w-2.5 rounded-full bg-amber-400';
            liveText.textContent = 'Gecikmeli';
            livePill.className = 'inline-flex items-center gap-2 rounded-full bg-amber-500/15 text-amber-300 px-3 py-1.5 text-sm font-semibold';
        }
    }

    async function fetchData() {
        if (inflight) inflight.abort();
        inflight = new AbortController();
        const url = new URL(@json(route('dashboard.data')), window.location.origin);
        url.searchParams.set('store_id', storeId);
        try {
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }, signal: inflight.signal });
            if (!res.ok) throw new Error('fail');
            const json = await res.json();
            if (json && json.data) { state = json.data; render(); }
        } catch (e) {}
    }

    function stopPolling() { if (timer) clearInterval(timer); timer = null; }
    function startPolling() { stopPolling(); setLive(false); timer = setInterval(fetchData, 12000); }

    async function setupRealtime() {
        const key = @json(config('broadcasting.connections.reverb.key'));
        const host = @json(config('broadcasting.connections.reverb.options.host'));
        const port = Number(@json(config('broadcasting.connections.reverb.options.port')) || 8080);
        const scheme = @json(config('broadcasting.connections.reverb.options.scheme')) || 'http';
        const authEndpoint = @json(url('/broadcasting/auth'));
        startPolling();
        if (!key) return;
        if (!window.Pusher) {
            await new Promise((r) => { const s = document.createElement('script'); s.src = 'https://js.pusher.com/8.4.0/pusher.min.js'; s.onload = r; s.onerror = r; document.head.appendChild(s); });
        }
        if (!window.Pusher) return;
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const pusher = new window.Pusher(key, {
            wsHost: host, wsPort: port, wssPort: port, forceTLS: scheme === 'https',
            enabledTransports: scheme === 'https' ? ['wss'] : ['ws'], cluster: '', authEndpoint, auth: { headers: { 'X-CSRF-TOKEN': csrf } },
        });
        pusher.connection.bind('connected', () => { stopPolling(); setLive(true); });
        ['disconnected', 'unavailable', 'failed', 'error'].forEach(ev =>
            pusher.connection.bind(ev, () => { if (!timer) startPolling(); }));
        const ch = pusher.subscribe(`private-store.${storeId}`);
        ch.bind('store.prices.updated', fetchData);
        ch.bind('market.quotes.updated', fetchData);
    }

    function tickClock() {
        clock.textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    render();
    setupRealtime();
    tickClock(); setInterval(tickClock, 1000);
    setInterval(() => {
        const payload = (state.store_prices || {})[currentSource] || null;
        updatedAgo.textContent = payload ? timeAgo(payload.calculated_at || state.generated_at) : '';
    }, 10000);
})();
</script>
</body>
</html>
