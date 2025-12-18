<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->enum('instrument', ['HAS', 'ONS']);
            $table->decimal('bid', 18, 6)->nullable();
            $table->decimal('ask', 18, 6)->nullable();
            $table->string('currency', 8);
            $table->timestamp('fetched_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'instrument', 'fetched_at'], 'idx_source_instrument_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_quotes');
    }
};

