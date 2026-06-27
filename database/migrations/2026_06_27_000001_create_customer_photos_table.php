<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto rumah pelanggan — alat bantu wayfinding karena alamat perkampungan
     * tidak presisi (rumah tampak depan, patokan gang/jalan). Beberapa foto per
     * pelanggan → tabel tersendiri, bukan kolom tunggal.
     *
     * `customer_id` cascadeOnDelete (foto = artefak pelanggan). `uploaded_by`
     * nullOnDelete (jaga riwayat: siapa yang unggah — admin/teknisi — tetap
     * tercatat walau penggunanya kelak dihapus). Simpan path file, bukan blob;
     * file fisik di disk `public` (butuh `php artisan storage:link`).
     * Append-only: hanya `created_at` (ikut pola tabel log), tanpa `updated_at`.
     */
    public function up(): void
    {
        Schema::create('customer_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_photos');
    }
};
