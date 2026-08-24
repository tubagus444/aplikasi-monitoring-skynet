<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft delete pelanggan — data tidak dihapus permanen, hanya ditandai
     * `deleted_at`. Admin bisa memulihkan pelanggan yang salah dihapus.
     * Relasi (`damage_reports.customer_id`, `customer_photos`) tetap utuh
     * karena baris pelanggan masih ada di database.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
