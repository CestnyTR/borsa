<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
        'last_gold_prices_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_gold_prices_at' => 'datetime',
    ];

    public function goldSettings(): HasMany
    {
        return $this->hasMany(CompanyGoldSetting::class, 'company_id');
    }
}
