<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoldSource extends Model
{
    protected $fillable = [
        'name',
        'code',
        'base_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rawPrices(): HasMany
    {
        return $this->hasMany(GoldRawPrice::class, 'source_id');
    }
}
