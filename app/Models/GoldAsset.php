<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoldAsset extends Model
{
    protected $fillable = [
        'code',
        'name',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rawPrices(): HasMany
    {
        return $this->hasMany(GoldRawPrice::class, 'asset_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(GoldPrice::class, 'asset_id');
    }

    public function companySettings(): HasMany
    {
        return $this->hasMany(CompanyGoldSetting::class, 'asset_id');
    }
}
