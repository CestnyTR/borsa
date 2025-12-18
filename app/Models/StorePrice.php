<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'source_id',
        'product_id',
        'bid',
        'ask',
        'calculated_at',
        'basis',
    ];

    protected function casts(): array
    {
        return [
            'bid' => 'decimal:6',
            'ask' => 'decimal:6',
            'calculated_at' => 'datetime',
            'basis' => 'array',
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

