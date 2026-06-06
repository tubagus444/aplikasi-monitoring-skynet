<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Kabel Putus',        'description' => 'Kabel jaringan terputus di lapangan'],
            ['name' => 'Router Bermasalah',  'description' => 'Router tidak merespons atau restart terus'],
            ['name' => 'Access Point Mati',  'description' => 'Access point tidak memancarkan sinyal'],
            ['name' => 'Internet Lambat',    'description' => 'Kecepatan internet di bawah normal'],
            ['name' => 'Gangguan Listrik',   'description' => 'Perangkat mati akibat masalah daya listrik'],
        ];

        foreach ($types as $type) {
            DB::table('damage_types')->insert([
                'name'        => $type['name'],
                'description' => $type['description'],
                'created_at'  => now(),
            ]);
        }
    }
}