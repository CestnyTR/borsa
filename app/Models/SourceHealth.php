<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceHealth extends Model
{
    protected $table = 'source_health';

    protected $fillable = [
        'source_id',
        'domain',
        'score',
        'last_success_at',
        'last_failure_at',
        'fail_count',
        'avg_latency_ms',
        'last_error',
    ];

    protected $casts = [
        'score' => 'integer',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'fail_count' => 'integer',
        'avg_latency_ms' => 'integer',
    ];
}
