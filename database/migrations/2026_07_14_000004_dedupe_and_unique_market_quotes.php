<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eski append-log satırlarını temizle: kaynak+enstrüman başına en yeni
        // satırı tut, gerisini sil. Böylece upsert için tablo tek-satıra iner.
        DB::statement(
            'DELETE FROM market_quotes WHERE id NOT IN '
            . '(SELECT MAX(id) FROM market_quotes GROUP BY source_id, instrument)'
        );

        Schema::table('market_quotes', function (Blueprint $table) {
            $table->unique(['source_id', 'instrument']);
        });
    }

    public function down(): void
    {
        Schema::table('market_quotes', function (Blueprint $table) {
            $table->dropUnique(['source_id', 'instrument']);
        });
    }
};
