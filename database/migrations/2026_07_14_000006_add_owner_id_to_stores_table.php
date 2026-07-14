<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // SaaS: mağazanın sahibi (hesap). Plan limitleri (max_stores) sahip
            // bazında uygulanır.
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Mevcut mağazaları ilk admin kullanıcıya bağla.
        $owner = User::query()->where('is_admin', true)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($owner) {
            Schema::disableForeignKeyConstraints();
            \DB::table('stores')->whereNull('owner_id')->update(['owner_id' => $owner->id]);
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
