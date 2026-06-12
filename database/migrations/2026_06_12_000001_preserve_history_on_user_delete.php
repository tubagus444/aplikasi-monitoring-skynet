<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cegah penghapusan pengguna ikut menghapus riwayat / jejak audit.
     *
     * Sebelumnya `damage_reports.created_by` & `work_logs.technician_id` memakai
     * cascadeOnDelete: menghapus seorang pengguna ikut MENGHAPUS laporan yang ia
     * buat dan work log yang ia kerjakan — riwayat selesai (halaman Riwayat + PDF)
     * kehilangan datanya secara diam-diam.
     *
     * Diubah ke nullOnDelete: pengguna tetap boleh dihapus, tetapi laporan & work
     * log TETAP ADA; kolom penulis/teknisi-nya menjadi NULL (ditampilkan "—" atau
     * "Teknisi dihapus"). Hanya dua kolom bernilai histori ini yang dilonggarkan.
     *
     * `task_assignments` & `location_logs` SENGAJA tetap cascade: assignment hanya
     * penanda "sedang ditugaskan" (bila teknisinya hilang, penugasannya memang tak
     * relevan lagi), dan location log hanyalah jejak GPS realtime, bukan arsip.
     */
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->unsignedBigInteger('technician_id')->nullable()->change();
            $table->foreign('technician_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->unsignedBigInteger('technician_id')->nullable(false)->change();
            $table->foreign('technician_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
