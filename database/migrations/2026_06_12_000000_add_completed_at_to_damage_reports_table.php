<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waktu selesai sebenarnya sebuah laporan. Sebelumnya disimpulkan dari
     * `updated_at`, yang ikut berubah saat laporan diedit setelah selesai —
     * merusak statistik "selesai hari ini", filter periode riwayat, dan durasi.
     * Kolom khusus ini jadi sumber kebenaran waktu selesai (di-set saat transisi
     * status ke "selesai" di TaskController).
     */
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        // Backfill data lama: pakai updated_at sebagai estimasi waktu selesai
        DB::table('damage_reports')
            ->where('status', 'selesai')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
