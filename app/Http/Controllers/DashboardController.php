<?php

namespace App\Http\Controllers;

use App\Models\MarketQuote;
use App\Models\Product;
use App\Models\Source;
use App\Models\Store;
use App\Models\StorePrice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\MarketData\MarketQuoteService;
use App\Pricing\StorePricingService;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user?->can('admin') ?? false;

        $stores = Store::query()
            ->when(!$isAdmin, fn ($q) => $q->where('is_active', true)->orderBy('id'))
            ->when($isAdmin, fn ($q) => $q->orderBy('name'))
            ->get();

        $selectedStore = $this->resolveSelectedStore($request, $stores, $isAdmin);

        $payload = $this->buildPayload($selectedStore);

        return view('dashboard.index', [
            'stores' => $stores,
            'selectedStore' => $selectedStore,
            'initial' => $payload,
            'isAdmin' => $isAdmin,
            'brandColor' => $selectedStore?->brandColor() ?? Store::DEFAULT_BRAND_COLOR,
        ]);
    }

    /**
     * Müşteriye dönük tam ekran fiyat panosu (canlı, read-only).
     */
    public function board(Request $request, Store $store)
    {
        $user = $request->user();
        $isAdmin = $user?->can('admin') ?? false;

        if (!$isAdmin && !($user && $user->store_id === $store->id)) {
            abort(403);
        }

        return view('board', [
            'store' => $store,
            'initial' => $this->buildPayload($store),
            'brandColor' => $store->brandColor(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user?->can('admin') ?? false;

        $storeId = (int) $request->query('store_id', 0);

        if (!$isAdmin) {
            $store = null;
            if ($user && $user->store_id) {
                $store = Store::query()->where('id', $user->store_id)->where('is_active', true)->first();
            }
            if (!$store) {
                return response()->json(['data' => null]);
            }

            return response()->json(['data' => $this->buildPayload($store)]);
        }

        $store = Store::query()->findOrFail($storeId ?: Store::query()->min('id'));
        return response()->json(['data' => $this->buildPayload($store)]);
    }

    public function fetchNow(Request $request, MarketQuoteService $marketQuoteService, StorePricingService $storePricingService)
    {
        try {
            $marketQuoteService->fetchAndStoreActiveSources();
            $storePricingService->computeAndStore();

            return redirect()
                ->route('dashboard')
                ->with('success', 'Veri çekme ve hesaplama tamamlandı.');
        } catch (\Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Veri çekme sırasında hata: ' . $e->getMessage());
        }
    }

    private function resolveSelectedStore(Request $request, $stores, bool $isAdmin): ?Store
    {
        if ($stores->isEmpty()) {
            return null;
        }

        if (!$isAdmin) {
            $user = $request->user();
            if ($user && $user->store_id) {
                $store = Store::query()->where('id', $user->store_id)->where('is_active', true)->first();
                if ($store) {
                    return $store;
                }
            }
            return null;
        }

        $requested = (int) $request->query('store_id', 0);
        if ($requested > 0 && $isAdmin) {
            $store = Store::query()->find($requested);
            if ($store) {
                $request->session()->put('dashboard.store_id', $store->id);
                return $store;
            }
        }

        $sessionId = (int) $request->session()->get('dashboard.store_id', 0);
        if ($sessionId > 0 && $isAdmin) {
            $store = Store::query()->find($sessionId);
            if ($store) {
                return $store;
            }
        }

        return $stores->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(?Store $store): array
    {
        $now = CarbonImmutable::now();

        $sources = Source::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $products = Product::query()->orderBy('code')->get(['id', 'code', 'name']);

        $market = [];
        foreach ($sources as $source) {
            foreach (['HAS', 'ONS'] as $instrument) {
                $cached = Cache::get("market:latest:{$source->code}:{$instrument}");
                if (is_array($cached)) {
                    if (!isset($cached['source_instrument'])) {
                        $cached['source_instrument'] = $this->sourceInstrumentFromPayload(
                            (string) ($cached['instrument'] ?? $instrument),
                            is_array($cached['raw_payload'] ?? null) ? $cached['raw_payload'] : null
                        );
                    }
                    $market[$source->code]['name'] = $source->name;
                    $market[$source->code]['instruments'][$instrument] = $this->withStale($cached, 'fetched_at', $now);
                }
            }
        }

        if ($market === []) {
            $market = $this->marketFromDb($sources, $now);
        }

        $storePrices = [];
        if ($store) {
            foreach ($sources as $source) {
                $cached = Cache::get("store:prices:latest:{$store->id}:{$source->code}");
                if (is_array($cached)) {
                    $storePrices[$source->code] = $this->withStale($cached, 'calculated_at', $now);
                }
            }

            if ($storePrices === []) {
                $storePrices = $this->storePricesFromDb($store->id, $now);
            }
        }

        return [
            'store' => $store ? ['id' => $store->id, 'name' => $store->name] : null,
            'sources' => $sources->map(fn ($s) => ['code' => $s->code, 'name' => $s->name])->values()->all(),
            'products' => $products->map(fn ($p) => ['code' => $p->code, 'name' => $p->name])->values()->all(),
            'market' => $market,
            'store_prices' => $storePrices,
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withStale(array $data, string $timestampKey, CarbonImmutable $now): array
    {
        $ts = $data[$timestampKey] ?? null;
        try {
            $parsed = is_string($ts) ? CarbonImmutable::parse($ts) : null;
        } catch (\Throwable) {
            $parsed = null;
        }

        $stale = $parsed ? $parsed->diffInSeconds($now) > 90 : true;

        return array_merge($data, [
            'stale' => $stale,
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Source> $sources
     * @return array<string, mixed>
     */
    private function marketFromDb($sources, CarbonImmutable $now): array
    {
        $latestIds = MarketQuote::query()
            ->select('source_id', 'instrument', DB::raw('MAX(id) as max_id'))
            ->groupBy('source_id', 'instrument');

        $rows = MarketQuote::query()
            ->joinSub($latestIds, 'latest', function ($join) {
                $join->on('market_quotes.id', '=', 'latest.max_id');
            })
            ->get(['market_quotes.*'])
            ->keyBy(fn ($q) => "{$q->source_id}:{$q->instrument}");

        $result = [];
        foreach ($sources as $source) {
            foreach (['HAS', 'ONS'] as $instrument) {
                $row = $rows->get("{$source->id}:{$instrument}");
                if (!$row) {
                    continue;
                }

                $result[$source->code]['name'] = $source->name;
                $result[$source->code]['instruments'][$instrument] = $this->withStale([
                    'id' => $row->id,
                    'source' => $source->code,
                    'instrument' => $instrument,
                    'source_instrument' => $this->sourceInstrumentFromPayload($instrument, $row->raw_payload),
                    'bid' => $row->bid !== null ? (float) $row->bid : null,
                    'ask' => $row->ask !== null ? (float) $row->ask : null,
                    'currency' => (string) $row->currency,
                    'fetched_at' => $row->fetched_at?->toIso8601String(),
                ], 'fetched_at', $now);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function storePricesFromDb(int $storeId, CarbonImmutable $now): array
    {
        $rows = StorePrice::query()
            ->where('store_id', $storeId)
            ->with(['source:id,code', 'product:id,code,name'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $sourceCode = $row->source->code;
            $productCode = $row->product->code;

            $result[$sourceCode] ??= [
                'store_id' => $storeId,
                'source' => $sourceCode,
                'calculated_at' => $row->calculated_at?->toIso8601String(),
                'products' => [],
            ];

            $result[$sourceCode]['products'][$productCode] = [
                'bid' => $row->bid !== null ? (float) $row->bid : null,
                'ask' => $row->ask !== null ? (float) $row->ask : null,
                'basis' => $row->basis,
            ];
        }

        foreach ($result as $sourceCode => $payload) {
            $result[$sourceCode] = $this->withStale($payload, 'calculated_at', $now);
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $rawPayload
     */
    private function sourceInstrumentFromPayload(string $instrument, ?array $rawPayload): string
    {
        if (is_array($rawPayload)) {
            $smb = $rawPayload['SMB'] ?? null;
            if (is_string($smb) && $smb !== '') {
                return $smb;
            }

            $parite = $rawPayload['parite'] ?? null;
            if (is_string($parite) && $parite !== '') {
                return $parite;
            }

            $baslik = $rawPayload['baslik'] ?? null;
            if (is_string($baslik) && $baslik !== '') {
                return $baslik;
            }
        }

        return $instrument;
    }
}
