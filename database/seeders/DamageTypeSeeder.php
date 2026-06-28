<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Kabel Putus',          'description' => 'Kabel jaringan terputus di lapangan'],
            ['name' => 'Router Bermasalah',    'description' => 'Router tidak merespons atau restart terus'],
            ['name' => 'Access Point Mati',    'description' => 'Access point tidak memancarkan sinyal'],
            ['name' => 'Internet Lambat',      'description' => 'Kecepatan internet di bawah normal'],
            ['name' => 'Gangguan Listrik',     'description' => 'Perangkat mati akibat masalah daya listrik'],
            ['name' => 'Konektor Rusak',       'description' => 'Konektor/splitter fiber rusak atau longgar'],
            ['name' => 'Redaman Tinggi',       'description' => 'Redaman (loss) sinyal optik di atas ambang normal'],
            ['name' => 'ODP/ODC Bermasalah',   'description' => 'Gangguan pada titik distribusi ODP atau ODC'],
            ['name' => 'Gangguan Cuaca',       'description' => 'Gangguan akibat hujan, petir, atau angin kencang'],
            ['name' => 'Maintenance Terjadwal','description' => 'Pemeliharaan rutin/preventif perangkat & jaringan'],
            ['name' => 'Penggantian Perangkat','description' => 'Penggantian perangkat lama atau rusak'],
        ];

        foreach ($types as $type) {
            DB::table('damage_types')->insert([
                'name'        => $type['name'],
                'description' => $type['description'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}