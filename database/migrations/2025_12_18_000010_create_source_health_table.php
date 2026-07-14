<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_health', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('domain', 16);
            $table->integer('score')->default(50);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('fail_count')->default(0);
            $table->unsignedInteger('avg_latency_ms')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_health');
    }
};
