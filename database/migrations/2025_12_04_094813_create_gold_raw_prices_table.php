<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gold_raw_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('gold_sources')->onDelete('cascade');
            $table->foreignId('asset_id')->constrained('gold_assets')->onDelete('cascade');
            $table->decimal('buy_price', 18, 4)->nullable();
            $table->decimal('sell_price', 18, 4);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['source_id', 'asset_id', 'fetched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_raw_prices');
    }
};
