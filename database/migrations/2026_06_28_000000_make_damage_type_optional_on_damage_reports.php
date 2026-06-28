<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadikan `damage_type_id` opsional & ubah perilaku hapus jenis gangguan.
     *
     * Semula FK `cascadeOnDelete`: menghapus satu jenis gangguan akan ikut MENGHAPUS
     * seluruh laporan/riwayat yang memakainya — bertentangan dengan prinsip "hapus data
     * master tak menghapus arsip" (sama seperti hapus user/pelanggan = `nullOnDelete`).
     *
     * Kolom dibuat `nullable()` + FK `nullOnDelete` sehingga:
     *  - Hapus jenis gangguan → laporan tetap utuh, kolomnya jadi NULL (tampil "—").
     *  - Kategori `pemeliharaan`/`jaringan` boleh tanpa jenis gangguan (pemeliharaan
     *    sering bukan "kerusakan"). Kategori `pelanggan` tetap wajib (validasi di form).
     */
    public function up(): void
    {
        // 1. Lepas FK lama (ON DELETE CASCADE).
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropForeign(['damage_type_id']);
        });

        // 2. Jadikan kolom nullable.
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreignId('damage_type_id')->nullable()->change();
        });

        // 3. Pasang FK baru (ON DELETE SET NULL).
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreign('damage_type_id')->references('id')->on('damage_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropForeign(['damage_type_id']);
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreignId('damage_type_id')->nullable(false)->change();
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreign('damage_type_id')->references('id')->on('damage_types')->cascadeOnDelete();
        });
    }
};
