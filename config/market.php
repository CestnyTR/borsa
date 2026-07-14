<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Streamed sources
    |--------------------------------------------------------------------------
    |
    | Sources whose quotes are pushed live by a long-running daemon (e.g.
    | `php artisan harem:listen`). The scheduled poller skips these so it does
    | not open a second connection to the same upstream (HAREM rate-limits
    | rapid connections with HTTP 429).
    |
    */

    'streamed_sources' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MARKET_STREAMED_SOURCES', 'HAREM'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Live broadcast debounce (milliseconds)
    |--------------------------------------------------------------------------
    |
    | Upstream ticks can arrive several times per second. The daemon coalesces
    | them and recomputes/broadcasts at most once per this interval.
    |
    */

    'stream_debounce_ms' => (int) env('MARKET_STREAM_DEBOUNCE_MS', 1000),

];
