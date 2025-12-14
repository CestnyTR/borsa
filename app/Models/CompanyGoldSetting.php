<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyGoldSetting extends Model
{
    protected $fillable = [
        'company_id',
        'asset_id',
        'weight_gram',
        'markup_percent',
        'markup_fixed',
        'rounding_step',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'weight_gram' => 'decimal:3',
        'markup_percent' => 'decimal:2',
        'markup_fixed' => 'decimal:2',
        'rounding_step' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(GoldAsset::class, 'asset_id');
    }
}
