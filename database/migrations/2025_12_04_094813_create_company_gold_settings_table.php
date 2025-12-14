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
        Schema::create('company_gold_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('asset_id')->constrained('gold_assets')->onDelete('cascade');
            $table->decimal('weight_gram', 10, 3);
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->decimal('markup_fixed', 10, 2)->default(0);
            $table->decimal('rounding_step', 10, 2)->default(0.01);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'asset_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_gold_settings');
    }
};
