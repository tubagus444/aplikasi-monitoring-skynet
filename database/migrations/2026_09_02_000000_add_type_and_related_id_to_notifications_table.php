<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom type & related_id ke tabel notifications agar notifikasi
     * bisa di-deep-link ke laporan terkait dan dibedakan per jenis.
     *
     * Nullable karena notifikasi lama (sebelum migrasi) tidak punya data ini.
     * related_id generic (bukan FK constrained) — laporan bisa dihapus,
     * notifikasi tetap terlihat (deep-link jadi no-op).
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('body');
            $table->unsignedBigInteger('related_id')->nullable()->after('type');
            $table->index(['user_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_read', 'created_at']);
            $table->dropColumn(['type', 'related_id']);
        });
    }
};
