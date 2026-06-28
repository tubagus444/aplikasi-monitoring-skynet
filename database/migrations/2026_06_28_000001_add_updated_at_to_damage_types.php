<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah `updated_at` ke `damage_types` agar punya jejak waktu ubah.
     *
     * Semula tabel hanya `created_at` (`useCurrent`) + model `$timestamps = false`
     * (cocok saat jenis cuma diisi seeder). Setelah ada menu CRUD (Rencana #5), admin
     * bisa mengedit jenis gangguan — jadi `updated_at` kini berguna. Nullable: baris
     * lama (seeder/raw insert) belum punya nilai; terisi otomatis saat pertama diedit.
     */
    public function up(): void
    {
        Schema::table('damage_types', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('damage_types', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
};
