<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelanggan sebagai entitas tersendiri. Sebelumnya nama/alamat pelanggan
     * hanya teks bebas yang diketik ulang tiap laporan — tak ada riwayat/kontak/
     * identitas. Kini laporan kategori "pelanggan" menunjuk ke baris di sini.
     *
     * `ip_address` = pengganti kode pelanggan (SkyNet tak punya ID formal, tiap
     * pelanggan punya IP yang di-assign). `latitude`/`longitude` nullable — titik
     * rumah belum tersedia, diisi belakangan (klik di peta).
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('address');
            $table->string('ip_address')->nullable();
            $table->string('subscription_package')->nullable();
            $table->enum('status', ['aktif', 'isolir', 'berhenti'])->default('aktif');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->date('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
