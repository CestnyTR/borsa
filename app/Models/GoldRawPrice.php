<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldRawPrice extends Model
{
    protected $fillable = [
        'source_id',
        'asset_id',
        'buy_price',
        'sell_price',
        'fetched_at',
    ];

    protected $casts = [
        'buy_price' => 'decimal:4',
        'sell_price' => 'decimal:4',
        'fetched_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(GoldSource::class, 'source_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(GoldAsset::class, 'asset_id');
    }
}
