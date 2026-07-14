<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Tenant marka alanlari (SaaS: her magaza kendi panosunu markalar).
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('brand_color', 20)->nullable()->after('slug');
            $table->string('logo_url')->nullable()->after('brand_color');
        });

        // Mevcut magazalara slug backfill (pano public URL'i icin).
        foreach (Store::query()->whereNull('slug')->get() as $store) {
            $base = Str::slug($store->name) ?: 'magaza';
            $slug = $base;
            $i = 1;
            while (Store::query()->where('slug', $slug)->where('id', '!=', $store->id)->exists()) {
                $slug = $base . '-' . (++$i);
            }
            $store->forceFill(['slug' => $slug])->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['slug', 'brand_color', 'logo_url']);
        });
    }
};
