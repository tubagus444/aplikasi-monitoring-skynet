<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel master paket internet yang tersedia. Menggantikan kolom varchar
     * `subscription_package` di tabel `customers` yang sebelumnya diisi teks
     * bebas (rawan inkonsistensi). Dengan tabel master, data paket terstandarisasi
     * dan bisa dikelola admin via halaman CRUD tersendiri.
     *
     * `speed_mbps` = kecepatan nominal dalam Mbps (integer, untuk sorting/analisis).
     * `price` = harga bulanan dalam Rupiah (nullable — ISP RT/RW Net kecil kadang
     * belum punya harga baku per paket).
     */
    public function up(): void
    {
        Schema::create('internet_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('speed_mbps');
            $table->unsignedInteger('price')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_packages');
    }
};
