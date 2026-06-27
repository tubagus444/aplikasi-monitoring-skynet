<?php

namespace Database\Seeders;

use App\Enums\ReportCategory;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageReportSeeder extends Seeder
{
    public function run(): void
    {
        // admin id = 1, damage_type id 1-5
        // Laporan kategori "pelanggan": tiap entri membuat/menautkan satu pelanggan
        // (firstOrCreate by nama+alamat) lalu menyimpan snapshot nama/alamat di laporan.
        $pelanggan = [
            [
                'damage_type_id' => 1,
                'name'           => 'Pak Hendra',
                'phone'          => '081234567001',
                'address'        => 'Jl. Mawar No. 5, Cibitung',
                'notes'          => 'Kabel di tiang depan rumah putus sejak kemarin',
                'status'         => 'selesai',
            ],
            [
                'damage_type_id' => 2,
                'name'           => 'Bu Sari',
                'phone'          => '081234567002',
                'address'        => 'Jl. Melati No. 12, Tambun',
                'notes'          => 'Router sering restart sendiri tiap malam',
                'status'         => 'sedang_memperbaiki',
            ],
            [
                'damage_type_id' => 3,
                'name'           => 'Pak Doni',
                'phone'          => '081234567003',
                'address'        => 'Jl. Kenanga No. 3, Cikarang',
                'notes'          => 'Access point di RT 03 tidak menyala',
                'status'         => 'ditugaskan',
            ],
            [
                'damage_type_id' => 4,
                'name'           => 'Bu Rina',
                'phone'          => '081234567004',
                'address'        => 'Jl. Dahlia No. 8, Cibitung',
                'notes'          => 'Internet sangat lambat sejak 3 hari lalu',
                'status'         => 'ditugaskan',
            ],
            [
                'damage_type_id' => 5,
                'name'           => 'Pak Agus',
                'phone'          => '081234567005',
                'address'        => 'Jl. Anggrek No. 17, Tambun',
                'notes'          => 'Semua perangkat mati setelah mati listrik',
                'status'         => 'selesai',
            ],
            [
                'damage_type_id' => 1,
                'name'           => 'Bu Tini',
                'phone'          => '081234567006',
                'address'        => 'Jl. Flamboyan No. 2, Cikarang',
                'notes'          => 'Kabel tertarik kendaraan lewat',
                'status'         => 'sedang_memperbaiki',
            ],
        ];

        foreach ($pelanggan as $r) {
            $customer = Customer::firstOrCreate(
                ['name' => $r['name'], 'address' => $r['address']],
                ['phone' => $r['phone'], 'status' => 'aktif'],
            );

            DB::table('damage_reports')->insert([
                'created_by'     => 1,
                'damage_type_id' => $r['damage_type_id'],
                'category'       => ReportCategory::Pelanggan->value,
                'customer_id'    => $customer->id,
                'customer_name'  => $r['name'],
                'title'          => null,
                'address'        => $r['address'],
                'notes'          => $r['notes'],
                'status'         => $r['status'],
                'completed_at'   => $r['status'] === 'selesai' ? now() : null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        // Laporan tanpa pelanggan (kategori jaringan & pemeliharaan): customer_id NULL,
        // pakai title + address (lokasi/area terdampak).
        $nonPelanggan = [
            [
                'damage_type_id' => 3,
                'category'       => ReportCategory::Jaringan->value,
                'title'          => 'Kabel utama putus area Cibitung',
                'address'        => 'Backbone RT 03 / RW 05, Cibitung',
                'notes'          => 'Banyak pelanggan terdampak, kabel FO utama putus tertimpa pohon',
                'status'         => 'sedang_memperbaiki',
            ],
            [
                'damage_type_id' => 4,
                'category'       => ReportCategory::Pemeliharaan->value,
                'title'          => 'Pemeliharaan rutin perangkat POP Tambun',
                'address'        => 'POP Tambun',
                'notes'          => 'Pengecekan & pembersihan perangkat bulanan',
                'status'         => 'ditugaskan',
            ],
        ];

        foreach ($nonPelanggan as $r) {
            DB::table('damage_reports')->insert([
                'created_by'     => 1,
                'damage_type_id' => $r['damage_type_id'],
                'category'       => $r['category'],
                'customer_id'    => null,
                'customer_name'  => null,
                'title'          => $r['title'],
                'address'        => $r['address'],
                'notes'          => $r['notes'],
                'status'         => $r['status'],
                'completed_at'   => $r['status'] === 'selesai' ? now() : null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
