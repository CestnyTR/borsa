<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory;

    /** Varsayilan marka rengi (altin tonu). */
    public const DEFAULT_BRAND_COLOR = '#C9A227';

    protected $fillable = [
        'name',
        'slug',
        'brand_color',
        'logo_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_priced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // SaaS: her magaza icin benzersiz slug (pano public URL'i icin).
        static::saving(function (Store $store) {
            if (blank($store->slug) && filled($store->name)) {
                $base = Str::slug($store->name) ?: 'magaza';
                $slug = $base;
                $i = 1;
                while (static::query()->where('slug', $slug)->where('id', '!=', $store->id)->exists()) {
                    $slug = $base . '-' . (++$i);
                }
                $store->slug = $slug;
            }
        });
    }

    public function brandColor(): string
    {
        return $this->brand_color ?: self::DEFAULT_BRAND_COLOR;
    }

    /**
     * @return HasMany<StorePriceMultiplier, $this>
     */
    public function priceMultipliers(): HasMany
    {
        return $this->hasMany(StorePriceMultiplier::class);
    }
}
