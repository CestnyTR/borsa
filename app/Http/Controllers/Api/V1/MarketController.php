<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MarketQuote;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $sources = Source::query()->where('is_active', true)->get(['id', 'code', 'name']);

        $result = [];
        foreach ($sources as $source) {
            foreach (['HAS', 'ONS'] as $instrument) {
                $cached = Cache::get("market:latest:{$source->code}:{$instrument}");
                if (is_array($cached)) {
                    $result[$source->code]['name'] = $source->name;
                    $result[$source->code]['instruments'][$instrument] = $cached;
                }
            }
        }

        if ($result !== []) {
            return response()->json([
                'data' => $result,
            ]);
        }

        $latestIds = MarketQuote::query()
            ->select('source_id', 'instrument', DB::raw('MAX(id) as max_id'))
            ->groupBy('source_id', 'instrument');

        $rows = MarketQuote::query()
            ->joinSub($latestIds, 'latest', function ($join) {
                $join->on('market_quotes.id', '=', 'latest.max_id');
            })
            ->with('source:id,code,name')
            ->get(['market_quotes.*']);

        foreach ($rows as $quote) {
            $result[$quote->source->code]['name'] = $quote->source->name;
            $result[$quote->source->code]['instruments'][(string) $quote->instrument] = [
                'id' => $quote->id,
                'source' => $quote->source->code,
                'instrument' => (string) $quote->instrument,
                'bid' => $quote->bid !== null ? (float) $quote->bid : null,
                'ask' => $quote->ask !== null ? (float) $quote->ask : null,
                'currency' => (string) $quote->currency,
                'fetched_at' => $quote->fetched_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}

