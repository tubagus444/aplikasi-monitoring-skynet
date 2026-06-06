<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkLogSeeder extends Seeder
{
    public function run(): void
    {
        $logs = [
            [
                'report_id'      => 1,
                'technician_id'  => 2,
                'status'         => 'selesai',
                'description'    => 'Kabel sudah disambung dan diperkuat dengan isolasi',
            ],
            [
                'report_id'      => 2,
                'technician_id'  => 3,
                'status'         => 'sedang_memperbaiki',
                'description'    => 'Sedang pengecekan konfigurasi router di lokasi',
            ],
            [
                'report_id'      => 5,
                'technician_id'  => 3,
                'status'         => 'selesai',
                'description'    => 'Semua perangkat sudah dinyalakan ulang dan normal',
            ],
            [
                'report_id'      => 6,
                'technician_id'  => 4,
                'status'         => 'sedang_memperbaiki',
                'description'    => 'Proses penarikan kabel baru sedang berlangsung',
            ],
        ];

        foreach ($logs as $log) {
            DB::table('work_logs')->insert([
                ...$log,
                'logged_at' => now(),
            ]);
        }
    }
}