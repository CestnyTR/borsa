<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'instrument',
        'bid',
        'ask',
        'currency',
        'fetched_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'bid' => 'decimal:6',
            'ask' => 'decimal:6',
            'fetched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}

