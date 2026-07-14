<?php

namespace App\Console\Commands;

use App\MarketData\DTO\QuoteDto;
use App\MarketData\MarketQuoteService;
use App\MarketData\Support\HaremSocketReader;
use App\MarketData\Support\NumberParser;
use App\Pricing\StorePricingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Long-running daemon: holds a single Socket.IO connection to HAREM and pushes
 * every price tick through the normal pipeline (store quotes -> recompute store
 * prices -> broadcast), so the dashboard updates live. Run under supervisor.
 */
class HaremListenCommand extends Command
{
    protected $signature = 'harem:listen';

    protected $description = 'Stream HAREM prices over Socket.IO and broadcast them live.';

    private const SOURCE_CODE = 'HAREM';

    private bool $stopping = false;

    /** @var array<string, mixed>|null latest tick awaiting a flush */
    private ?array $pending = null;

    private float $lastFlushAt = 0.0;

    private bool $tickSucceeded = false;

    public function handle(
        HaremSocketReader $reader,
        MarketQuoteService $marketQuoteService,
        StorePricingService $storePricingService
    ): int {
        $this->registerSignals();

        $debounceMs = (int) config('market.stream_debounce_ms', 1000);
        $backoff = 1;
        $maxBackoff = 10;

        $this->info('[harem:listen] starting');
        // Cron'a "HAREM'i ben besliyorum" sinyali.
        MarketQuoteService::touchStreamHeartbeat(self::SOURCE_CODE);

        while (!$this->stopping) {
            $this->tickSucceeded = false;
            try {
                $reader->listen(
                    onTick: function (array $data) use ($marketQuoteService, $storePricingService, $debounceMs): void {
                        $this->pending = $data;
                        $this->tickSucceeded = true;
                        $this->maybeFlush($marketQuoteService, $storePricingService, $debounceMs);
                    },
                    shouldStop: function (): bool {
                        $this->dispatchSignals();
                        return $this->stopping;
                    },
                    onIdle: function () use ($marketQuoteService, $storePricingService, $debounceMs): void {
                        // Bağlantı ayakta — heartbeat'i taze tut ve varsa bekleyen tick'i boşalt.
                        MarketQuoteService::touchStreamHeartbeat(self::SOURCE_CODE);
                        $this->maybeFlush($marketQuoteService, $storePricingService, $debounceMs);
                    },
                );
            } catch (\Throwable $e) {
                if ($this->stopping) {
                    break;
                }

                Log::warning('HaremListenCommand: connection dropped', ['message' => $e->getMessage()]);
                $this->warn("[harem:listen] {$e->getMessage()} — reconnecting in {$backoff}s");

                $this->interruptibleSleep($backoff);
                $backoff = $this->tickSucceeded ? 1 : min($maxBackoff, $backoff * 2);
                continue;
            }
        }

        $this->info('[harem:listen] stopped');

        return self::SUCCESS;
    }

    private function maybeFlush(
        MarketQuoteService $marketQuoteService,
        StorePricingService $storePricingService,
        int $debounceMs
    ): void {
        if ($this->pending === null) {
            return;
        }

        if ((microtime(true) - $this->lastFlushAt) * 1000 < $debounceMs) {
            return;
        }

        $data = $this->pending;
        $this->pending = null;
        $this->lastFlushAt = microtime(true);
        MarketQuoteService::touchStreamHeartbeat(self::SOURCE_CODE);

        try {
            $quotes = $this->buildQuotes($data);
            if ($quotes === []) {
                return;
            }

            $marketQuoteService->ingestQuotes(self::SOURCE_CODE, $quotes);
            // Recompute store prices from the fresh market cache and broadcast
            // on the per-store channels the dashboard subscribes to.
            $storePricingService->computeAndStore();
        } catch (\Throwable $e) {
            Log::error('HaremListenCommand: flush failed', ['message' => $e->getMessage()]);
            $this->error("[harem:listen] flush failed: {$e->getMessage()}");
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, QuoteDto>
     */
    private function buildQuotes(array $data): array
    {
        $fetchedAt = CarbonImmutable::now();

        $hasRow = HaremSocketReader::extractRow($data, ['ALTIN', 'HASALTIN', 'HAS_ALTIN', 'HAS'], ['ALTIN', 'HASALTIN', 'HAS']);
        $onsRow = HaremSocketReader::extractRow($data, ['ONS', 'XAUUSD'], ['ONS', 'XAUUSD', 'XAU/USD']);

        $quotes = [];

        if ($hasRow !== null) {
            $quotes[] = new QuoteDto(
                sourceCode: self::SOURCE_CODE,
                instrument: 'HAS',
                bid: NumberParser::parseLocaleNumber($hasRow['alis'] ?? null),
                ask: NumberParser::parseLocaleNumber($hasRow['satis'] ?? null),
                currency: 'TRY',
                fetchedAt: $fetchedAt,
                rawPayload: $hasRow
            );
        }

        if ($onsRow !== null) {
            $quotes[] = new QuoteDto(
                sourceCode: self::SOURCE_CODE,
                instrument: 'ONS',
                bid: NumberParser::parseLocaleNumber($onsRow['alis'] ?? null),
                ask: NumberParser::parseLocaleNumber($onsRow['satis'] ?? null),
                currency: 'USD',
                fetchedAt: $fetchedAt,
                rawPayload: $onsRow
            );
        }

        return $quotes;
    }

    private function registerSignals(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        // Deliver signals immediately so a blocking read/select is interrupted
        // and the daemon can stop within the loop rather than being killed.
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        $handler = function (): void {
            $this->stopping = true;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }

    private function dispatchSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    private function interruptibleSleep(int $seconds): void
    {
        for ($i = 0; $i < $seconds && !$this->stopping; $i++) {
            sleep(1);
            $this->dispatchSignals();
        }
    }
}
