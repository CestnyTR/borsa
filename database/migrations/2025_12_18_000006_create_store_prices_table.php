<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('bid', 18, 6)->nullable();
            $table->decimal('ask', 18, 6)->nullable();
            $table->timestamp('calculated_at');
            $table->json('basis')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'source_id', 'product_id'], 'uq_store_source_product');
            $table->index(['store_id', 'source_id', 'product_id', 'calculated_at'], 'idx_store_source_product_calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_prices');
    }
};

