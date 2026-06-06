<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageReportSeeder extends Seeder
{
    public function run(): void
    {
        // admin id = 1, damage_type id 1-5
        $reports = [
            [
                'created_by'      => 1,
                'damage_type_id'  => 1,
                'customer_name'   => 'Pak Hendra',
                'address'         => 'Jl. Mawar No. 5, Cibitung',
                'notes'           => 'Kabel di tiang depan rumah putus sejak kemarin',
                'status'          => 'selesai',
            ],
            [
                'created_by'      => 1,
                'damage_type_id'  => 2,
                'customer_name'   => 'Bu Sari',
                'address'         => 'Jl. Melati No. 12, Tambun',
                'notes'           => 'Router sering restart sendiri tiap malam',
                'status'          => 'sedang_memperbaiki',
            ],
            [
                'created_by'      => 1,
                'damage_type_id'  => 3,
                'customer_name'   => 'Pak Doni',
                'address'         => 'Jl. Kenanga No. 3, Cikarang',
                'notes'           => 'Access point di RT 03 tidak menyala',
                'status'          => 'ditugaskan',
            ],
            [
                'created_by'      => 1,
                'damage_type_id'  => 4,
                'customer_name'   => 'Bu Rina',
                'address'         => 'Jl. Dahlia No. 8, Cibitung',
                'notes'           => 'Internet sangat lambat sejak 3 hari lalu',
                'status'          => 'ditugaskan',
            ],
            [
                'created_by'      => 1,
                'damage_type_id'  => 5,
                'customer_name'   => 'Pak Agus',
                'address'         => 'Jl. Anggrek No. 17, Tambun',
                'notes'           => 'Semua perangkat mati setelah mati listrik',
                'status'          => 'selesai',
            ],
            [
                'created_by'      => 1,
                'damage_type_id'  => 1,
                'customer_name'   => 'Bu Tini',
                'address'         => 'Jl. Flamboyan No. 2, Cikarang',
                'notes'           => 'Kabel tertarik kendaraan lewat',
                'status'          => 'sedang_memperbaiki',
            ],
        ];

        foreach ($reports as $report) {
            DB::table('damage_reports')->insert([
                ...$report,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}