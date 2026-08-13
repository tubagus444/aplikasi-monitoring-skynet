<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti kolom `subscription_package` (varchar teks bebas) dengan FK
     * `internet_package_id` yang menunjuk ke tabel master `internet_packages`.
     *
     * FK menggunakan `nullOnDelete` — hapus paket TIDAK menghapus pelanggan;
     * kolomnya jadi NULL (tampil "—"). Konsisten dengan pola `damage_type_id`
     * di `damage_reports`.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('internet_package_id')
                  ->nullable()
                  ->after('ip_address')
                  ->constrained('internet_packages')
                  ->nullOnDelete();

            $table->dropColumn('subscription_package');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internet_package_id');
            $table->string('subscription_package')->nullable()->after('ip_address');
        });
    }
};
