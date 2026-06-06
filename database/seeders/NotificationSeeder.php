<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notifs = [
            [
                'user_id' => 1,
                'title'   => 'Laporan Baru Masuk',
                'body'    => 'Laporan kerusakan dari Bu Rina telah ditambahkan.',
                'is_read' => false,
            ],
            [
                'user_id' => 1,
                'title'   => 'Perbaikan Selesai',
                'body'    => 'Teknisi Budi telah menyelesaikan perbaikan di Jl. Mawar.',
                'is_read' => true,
            ],
            [
                'user_id' => 2,
                'title'   => 'Tugas Baru',
                'body'    => 'Kamu ditugaskan untuk laporan kerusakan di Jl. Dahlia.',
                'is_read' => false,
            ],
            [
                'user_id' => 3,
                'title'   => 'Tugas Baru',
                'body'    => 'Kamu ditugaskan untuk laporan kerusakan di Jl. Melati.',
                'is_read' => true,
            ],
            [
                'user_id' => 4,
                'title'   => 'Tugas Baru',
                'body'    => 'Kamu ditugaskan untuk laporan kerusakan di Jl. Kenanga.',
                'is_read' => false,
            ],
        ];

        foreach ($notifs as $notif) {
            DB::table('notifications')->insert([
                ...$notif,
                'created_at' => now(),
            ]);
        }
    }
}