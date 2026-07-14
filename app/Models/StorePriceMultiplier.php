<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePriceMultiplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'source_id',
        'instrument',
        'product_id',
        'side',
        'margin_percent',
        'margin_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'margin_percent' => 'float',
            'margin_amount' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

