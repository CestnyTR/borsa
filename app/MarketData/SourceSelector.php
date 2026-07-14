<?php

namespace App\MarketData;

use App\Models\Source;
use App\Services\SourceHealthService;
use Illuminate\Support\Collection;

class SourceSelector
{
    public function __construct(private readonly SourceHealthService $healthService = new SourceHealthService())
    {
    }

    /**
     * @return Collection<int, Source>
     */
    public function orderedSources(): Collection
    {
        $sources = Source::query()->where('is_active', true)->get();
        if ($sources->isEmpty()) {
            return $sources;
        }

        $orderedIds = $this->healthService->orderSourceIds('market', $sources->pluck('id')->all());

        return $sources->sortBy(function (Source $source) use ($orderedIds) {
            return array_search($source->id, $orderedIds, true) ?? PHP_INT_MAX;
        })->values();
    }
}
