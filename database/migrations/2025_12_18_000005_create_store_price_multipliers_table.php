<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_price_multipliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->enum('instrument', ['HAS', 'ONS']);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('side', ['BID', 'ASK']);
            $table->decimal('multiplier', 18, 6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'source_id', 'instrument', 'product_id', 'side'], 'uq_store_source_instr_prod_side');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_price_multipliers');
    }
};

