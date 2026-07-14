<?php

namespace App\Services;

use App\Models\SourceHealth;
use Carbon\CarbonImmutable;

class SourceHealthService
{
    public function recordSuccess(string $domain, int $sourceId, int $latencyMs): void
    {
        $now = CarbonImmutable::now();

        $health = SourceHealth::query()->firstOrNew([
            'domain' => $domain,
            'source_id' => $sourceId,
        ]);

        $health->score = $this->clampScore(($health->score ?? 50) + 2);
        $health->last_success_at = $now;
        $health->fail_count = (int) ($health->fail_count ?? 0);
        $health->avg_latency_ms = $this->averageLatency((int) ($health->avg_latency_ms ?? 0), $latencyMs);
        $health->last_error = null;

        $health->save();
    }

    public function recordFailure(string $domain, int $sourceId, int $latencyMs, string $error): void
    {
        $now = CarbonImmutable::now();

        $health = SourceHealth::query()->firstOrNew([
            'domain' => $domain,
            'source_id' => $sourceId,
        ]);

        $health->score = $this->clampScore(($health->score ?? 50) - 10);
        $health->last_failure_at = $now;
        $health->fail_count = ((int) ($health->fail_count ?? 0)) + 1;
        $health->avg_latency_ms = $this->averageLatency((int) ($health->avg_latency_ms ?? 0), $latencyMs);
        $health->last_error = $error;

        $health->save();
    }

    /**
     * @param array<int, int> $sourceIds
     * @return array<int, int>
     */
    public function orderSourceIds(string $domain, array $sourceIds): array
    {
        if ($sourceIds === []) {
            return [];
        }

        $healthRows = SourceHealth::query()
            ->where('domain', $domain)
            ->whereIn('source_id', $sourceIds)
            ->get()
            ->keyBy('source_id');

        usort($sourceIds, function (int $a, int $b) use ($healthRows): int {
            $scoreA = (int) ($healthRows[$a]->score ?? 50);
            $scoreB = (int) ($healthRows[$b]->score ?? 50);

            if ($scoreA === $scoreB) {
                return $a <=> $b;
            }

            return $scoreB <=> $scoreA;
        });

        return $sourceIds;
    }

    private function clampScore(int $score): int
    {
        return max(0, min(100, $score));
    }

    private function averageLatency(int $current, int $sample): int
    {
        if ($current <= 0) {
            return $sample;
        }

        return (int) round(($current * 0.7) + ($sample * 0.3));
    }
}
