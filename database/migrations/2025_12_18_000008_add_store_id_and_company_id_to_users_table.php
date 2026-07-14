<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('is_admin')->constrained('stores')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->after('store_id')->constrained('companies')->nullOnDelete();

            $table->index(['store_id']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['company_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn(['store_id', 'company_id']);
        });
    }
};
