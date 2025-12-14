<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldPrice extends Model
{
    protected $fillable = [
        'asset_id',
        'buy_price',
        'sell_price',
        'calculated_from',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'buy_price' => 'decimal:4',
        'sell_price' => 'decimal:4',
        'calculated_from' => 'array',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(GoldAsset::class, 'asset_id');
    }
}
