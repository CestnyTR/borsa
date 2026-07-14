<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_code',
        'status',
        'iyzico_ref',
        'iyzico_customer_ref',
        'current_period_ends_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Abonelik yürürlükte mi (aktif/deneme ve süresi geçmemiş).
     */
    public function isActive(): bool
    {
        if (!in_array($this->status, ['active', 'trialing'], true)) {
            return false;
        }

        if ($this->current_period_ends_at !== null && $this->current_period_ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
