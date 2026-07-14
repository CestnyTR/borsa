<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'store_id',
        'company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'store_id' => 'integer',
            'company_id' => 'integer',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Kullanıcının sahip olduğu mağazalar (SaaS hesap → mağazalar).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Store, $this>
     */
    public function ownedStores()
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function canAccessStore(int $storeId): bool
    {
        return $this->isAdmin() || ((int) $this->store_id === $storeId);
    }

    public function canAccessCompany(int $companyId): bool
    {
        return $this->isAdmin() || ((int) $this->company_id === $companyId);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Kullanıcının yürürlükteki plan kodu (aktif abonelik yoksa 'free').
     */
    public function planCode(): string
    {
        $sub = $this->subscription;
        if ($sub && $sub->isActive()) {
            return $sub->plan_code;
        }

        return (string) config('plans.default', 'free');
    }

    /**
     * Yürürlükteki planın config tanımı.
     *
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        $plans = (array) config('plans.plans', []);
        $code = $this->planCode();

        return $plans[$code] ?? $plans[config('plans.default', 'free')] ?? [];
    }

    /**
     * Plan özelliği/limiti (örn. 'max_stores', 'live_prices').
     */
    public function planFeature(string $key, mixed $default = null): mixed
    {
        return $this->plan()['features'][$key] ?? $default;
    }

    public function onPro(): bool
    {
        return $this->planCode() === 'pro';
    }
}
