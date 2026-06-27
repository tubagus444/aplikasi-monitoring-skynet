<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto bukti pekerjaan teknisi — lampiran kondisi sebelum/sesudah perbaikan
     * agar admin punya bukti visual pekerjaan benar dikerjakan (GPS membuktikan
     * teknisi ADA di lokasi; foto+catatan membuktikan PEKERJAANNYA). Diunggah dari
     * Android saat menangani tugas. Beberapa foto per laporan → tabel tersendiri.
     *
     * Pola sama dengan customer_photos: `report_id` cascadeOnDelete (foto = artefak
     * laporan), `uploaded_by` nullOnDelete (jaga jejak siapa pengunggah walau user
     * kelak dihapus). Simpan path file di disk `public`, bukan blob. Append-only:
     * hanya `created_at` (ikut pola tabel log), tanpa `updated_at`.
     */
    public function up(): void
    {
        Schema::create('report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('damage_reports')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_photos');
    }
};
