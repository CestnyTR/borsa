<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('last_priced_at')->nullable()->after('is_active');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('last_gold_prices_at')->nullable()->after('is_active');
        });

        Schema::table('gold_assets', function (Blueprint $table) {
            $table->timestamp('last_price_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('last_priced_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('last_gold_prices_at');
        });

        Schema::table('gold_assets', function (Blueprint $table) {
            $table->dropColumn('last_price_at');
        });
    }
};
