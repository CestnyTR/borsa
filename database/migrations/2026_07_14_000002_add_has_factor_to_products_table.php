<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Ürünün gram-altın içeriği (sunucu sabiti). Fiyat = HAS × has_factor × marj.
            $table->decimal('has_factor', 12, 6)->default(1)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_factor');
        });
    }
};
