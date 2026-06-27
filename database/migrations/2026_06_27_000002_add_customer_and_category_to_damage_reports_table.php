<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sambungkan laporan ke entitas pelanggan + beri kategori.
     *
     * - `category`: enum (default `pelanggan` → data lama semua = komplain pelanggan).
     *   Kategori `jaringan`/`pemeliharaan` tak punya pelanggan.
     * - `customer_id`: FK nullable, `nullOnDelete` — hapus pelanggan TIDAK menghapus
     *   laporan; kolom jadi NULL, snapshot `customer_name`/`address` tetap tampil
     *   (selaras prinsip "hapus data master tak menghapus arsip").
     * - `title`: judul untuk laporan non-pelanggan (kategori pelanggan pakai nama
     *   pelanggan sebagai headline lewat accessor `judul`).
     * - `customer_name` jadi NULLABLE: untuk laporan non-pelanggan kolom ini NULL,
     *   tapi TETAP ADA sebagai snapshot saat kategori pelanggan (riwayat & PDF tak
     *   ikut berubah bila data pelanggan kelak diperbarui).
     */
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->enum('category', ['pelanggan', 'jaringan', 'pemeliharaan'])
                  ->default('pelanggan')
                  ->after('damage_type_id');
            $table->foreignId('customer_id')->nullable()->after('category')
                  ->constrained('customers')->nullOnDelete();
            $table->string('title')->nullable()->after('customer_id');
            $table->string('customer_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['category', 'customer_id', 'title']);
            $table->string('customer_name')->nullable(false)->change();
        });
    }
};
