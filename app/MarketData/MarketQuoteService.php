<?php

namespace App\MarketData;

use App\MarketData\Clients\MarketDataClientFactory;
use App\Events\MarketQuotesUpdated;
use App\Models\MarketQuote;
use App\Models\Source;
use App\Services\SourceHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketQuoteService
{
    public function __construct(
        private readonly MarketDataClientFactory $clientFactory = new MarketDataClientFactory(),
        private readonly SourceSelector $sourceSelector = new SourceSelector(),
        private readonly SourceHealthService $healthService = new SourceHealthService(),
    ) {
    }

    public function fetchAndStoreActiveSources(int $cacheTtlSeconds = 90): void
    {
        $sources = $this->sourceSelector->orderedSources();
        $streamed = array_map('strtoupper', (array) config('market.streamed_sources', []));

        foreach ($sources as $source) {
            // Sources owned by a live daemon (e.g. HAREM) are pushed in real
            // time; skip them here so we don't open a second upstream
            // connection and trip its rate limit.
            if (in_array(strtoupper((string) $source->code), $streamed, true)) {
                continue;
            }

            $startedAt = microtime(true);
            try {
                $client = $this->clientFactory->make($source->code);
                $quotes = $client->fetchQuotes();

                $this->storeSourceQuotes($source, $quotes, $cacheTtlSeconds);

                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->healthService->recordSuccess('market', $source->id, $latencyMs);
            } catch (\Throwable $e) {
                $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->healthService->recordFailure('market', $source->id, $latencyMs, $e->getMessage());
                Log::warning('MarketQuoteService: fetch failed', [
                    'source' => $source->code,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->broadcastLatest(['admin.dashboard']);
    }

    /**
     * Persist a snapshot pushed by a live daemon and record source health.
     * Used by the streaming path (e.g. HaremListenCommand); the caller is
     * responsible for recomputing store prices / broadcasting.
     *
     * @param array<int, \App\MarketData\DTO\QuoteDto> $quotes
     */
    public function ingestQuotes(string $sourceCode, array $quotes, int $cacheTtlSeconds = 90): void
    {
        $source = Source::query()->where('code', $sourceCode)->first();
        if ($source === null) {
            throw new \RuntimeException("Unknown source: {$sourceCode}");
        }

        $startedAt = microtime(true);
        try {
            $this->storeSourceQuotes($source, $quotes, $cacheTtlSeconds);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->healthService->recordSuccess('market', $source->id, $latencyMs);
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->healthService->recordFailure('market', $source->id, $latencyMs, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Write one source's quotes to the DB and refresh their latest-cache.
     *
     * @param array<int, \App\MarketData\DTO\QuoteDto> $quotes
     */
    public function storeSourceQuotes(Source $source, array $quotes, int $cacheTtlSeconds = 90): void
    {
        foreach ($quotes as $quote) {
            $model = MarketQuote::query()->create([
                'source_id' => $source->id,
                'instrument' => $quote->instrument,
                'bid' => $quote->bid,
                'ask' => $quote->ask,
                'currency' => $quote->currency,
                'fetched_at' => $quote->fetchedAt,
                'raw_payload' => $quote->rawPayload,
            ]);

            Cache::put(
                $this->cacheKeyLatest($source->code, $quote->instrument),
                [
                    'id' => $model->id,
                    'source' => $source->code,
                    'instrument' => $quote->instrument,
                    'source_instrument' => $this->sourceInstrumentLabel($quote->instrument, $quote->rawPayload),
                    'bid' => $quote->bid,
                    'ask' => $quote->ask,
                    'currency' => $quote->currency,
                    'fetched_at' => $quote->fetchedAt->toIso8601String(),
                ],
                $cacheTtlSeconds
            );
        }
    }

    /**
     * Broadcast the latest snapshot across all sources on the given channels.
     *
     * @param array<int, string> $channelNames
     */
    public function broadcastLatest(array $channelNames): void
    {
        $latestQuotes = $this->latestQuotes();
        if ($latestQuotes === []) {
            return;
        }

        $lastUpdateAt = $this->latestFetchedAt($latestQuotes);
        event(new MarketQuotesUpdated(
            quotes: $latestQuotes,
            meta: [
                'emitted_at' => now()->toIso8601String(),
                'source_ids' => array_values(array_unique(array_column($latestQuotes, 'source_id'))),
                'trace_id' => (string) Str::uuid(),
                'last_update_at' => $lastUpdateAt,
            ],
            channelNames: $channelNames
        ));
    }

    public function cacheKeyLatest(string $sourceCode, string $instrument): string
    {
        return "market:latest:{$sourceCode}:{$instrument}";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestQuotes(): array
    {
        $latestIds = MarketQuote::query()
            ->select('source_id', 'instrument', DB::raw('MAX(id) as max_id'))
            ->groupBy('source_id', 'instrument');

        $rows = MarketQuote::query()
            ->joinSub($latestIds, 'latest', function ($join) {
                $join->on('market_quotes.id', '=', 'latest.max_id');
            })
            ->get(['market_quotes.*']);

        return $rows->map(fn (MarketQuote $quote) => [
            'id' => $quote->id,
            'source_id' => (int) $quote->source_id,
            'instrument' => (string) $quote->instrument,
            'bid' => $quote->bid !== null ? (float) $quote->bid : null,
            'ask' => $quote->ask !== null ? (float) $quote->ask : null,
            'currency' => (string) $quote->currency,
            'fetched_at' => $quote->fetched_at?->toIso8601String(),
        ])->values()->all();
    }

    /**
     * @param array<int, array<string, mixed>> $quotes
     */
    private function latestFetchedAt(array $quotes): ?string
    {
        $timestamps = array_filter(array_map(fn (array $q) => $q['fetched_at'] ?? null, $quotes));
        if ($timestamps === []) {
            return null;
        }

        $parsed = [];
        foreach ($timestamps as $ts) {
            try {
                $parsed[] = CarbonImmutable::parse($ts)->getTimestamp();
            } catch (\Throwable) {
                continue;
            }
        }
        if ($parsed === []) {
            return null;
        }
        $latest = max($parsed);
        return CarbonImmutable::createFromTimestamp($latest)->toIso8601String();
    }

    /**
     * @param array<string, mixed>|null $rawPayload
     */
    private function sourceInstrumentLabel(string $instrument, ?array $rawPayload): string
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
