<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationLogSeeder extends Seeder
{
    public function run(): void
    {
        // Simulasi titik GPS teknisi di area Kab. Bekasi
        $locations = [
            ['technician_id' => 2, 'report_id' => 1, 'latitude' => -6.2741, 'longitude' => 107.1254],
            ['technician_id' => 2, 'report_id' => 1, 'latitude' => -6.2743, 'longitude' => 107.1257],
            ['technician_id' => 3, 'report_id' => 2, 'latitude' => -6.2819, 'longitude' => 107.0892],
            ['technician_id' => 3, 'report_id' => 2, 'latitude' => -6.2821, 'longitude' => 107.0895],
            ['technician_id' => 4, 'report_id' => 6, 'latitude' => -6.3102, 'longitude' => 107.1423],
            ['technician_id' => 4, 'report_id' => 6, 'latitude' => -6.3105, 'longitude' => 107.1426],
        ];

        foreach ($locations as $loc) {
            DB::table('location_logs')->insert([
                ...$loc,
                'recorded_at' => now(),
            ]);
        }
    }
}