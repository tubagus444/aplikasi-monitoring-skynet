<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrasi DATA: jadikan pelanggan dari laporan lama sebagai entitas.
     *
     * Sebelum modul Pelanggan, nama+alamat hanya teks bebas di tiap laporan. Di sini
     * tiap kombinasi `customer_name`+`address` unik dijadikan satu baris `customers`,
     * lalu `damage_reports.customer_id` diisi menunjuk ke sana. Snapshot
     * `customer_name`/`address` di laporan SENGAJA dibiarkan (riwayat & PDF tak berubah
     * bila data pelanggan kelak diperbarui).
     *
     * `phone` dikosongkan ('') — data lama tak punya; admin lengkapi belakangan lewat
     * menu Pelanggan (form akan mewajibkannya saat diedit). `category` semua laporan
     * lama sudah otomatis `pelanggan` (default kolom saat ditambahkan).
     *
     * IDEMPOTENT: aman dijalankan berulang. Pelanggan dicari dulu by (name, address)
     * sebelum dibuat; hanya laporan dengan `customer_id` masih NULL yang diisi.
     */
    public function up(): void
    {
        $pairs = DB::table('damage_reports')
            ->whereNotNull('customer_name')
            ->whereNull('customer_id')
            ->select('customer_name', 'address')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $customerId = DB::table('customers')
                ->where('name', $pair->customer_name)
                ->where('address', $pair->address)
                ->value('id');

            if (! $customerId) {
                $customerId = DB::table('customers')->insertGetId([
                    'name'       => $pair->customer_name,
                    'phone'      => '',
                    'address'    => $pair->address,
                    'status'     => 'aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('damage_reports')
                ->whereNull('customer_id')
                ->where('customer_name', $pair->customer_name)
                ->where('address', $pair->address)
                ->update(['customer_id' => $customerId]);
        }
    }

    /**
     * Tidak dibalik di sini: rollback skema (migrasi sebelumnya) men-drop kolom
     * `customer_id` & tabel `customers` sepenuhnya, jadi data ini ikut hilang.
     * Menghapus baris pelanggan di sini berisiko ikut menghapus pelanggan yang
     * sudah diedit/dibuat manual admin — sengaja dibiarkan no-op.
     */
    public function down(): void
    {
        // no-op (lihat catatan di atas)
    }
};
