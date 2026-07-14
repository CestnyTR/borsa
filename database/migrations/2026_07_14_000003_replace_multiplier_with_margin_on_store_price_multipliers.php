<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_price_multipliers', function (Blueprint $table) {
            // Mağaza marjı: yüzde (%) ve sabit tutar (₺). Gram-içeriği ürün
            // sabitine (products.has_factor) taşındığı için çarpan kaldırıldı.
            // Fiyat = HAS × has_factor × (1 + margin_percent/100) + margin_amount
            $table->decimal('margin_percent', 8, 4)->default(0)->after('side');
            $table->decimal('margin_amount', 14, 4)->default(0)->after('margin_percent');
        });

        if (Schema::hasColumn('store_price_multipliers', 'multiplier')) {
            Schema::table('store_price_multipliers', function (Blueprint $table) {
                $table->dropColumn('multiplier');
            });
        }
    }

    public function down(): void
    {
        Schema::table('store_price_multipliers', function (Blueprint $table) {
            $table->decimal('multiplier', 12, 6)->default(1)->after('side');
            $table->dropColumn(['margin_percent', 'margin_amount']);
        });
    }
};
