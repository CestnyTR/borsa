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
        Schema::create('gold_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('gold_assets')->onDelete('cascade');
            $table->decimal('buy_price', 18, 4)->nullable();
            $table->decimal('sell_price', 18, 4);
            $table->json('calculated_from')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'valid_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
    }
};
