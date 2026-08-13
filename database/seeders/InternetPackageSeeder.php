<?php

namespace Database\Seeders;

use App\Models\InternetPackage;
use Illuminate\Database\Seeder;

class InternetPackageSeeder extends Seeder
{
    /**
     * Paket internet default SkyNet RT/RW Net. Harga contoh — bisa diedit
     * admin lewat halaman CRUD Paket Internet.
     */
    public function run(): void
    {
        $packages = [
            ['name' => '10 Mbps', 'speed_mbps' => 10, 'price' => 100000, 'description' => 'Paket hemat'],
            ['name' => '20 Mbps', 'speed_mbps' => 20, 'price' => 150000, 'description' => 'Paket standar'],
            ['name' => '30 Mbps', 'speed_mbps' => 30, 'price' => 200000, 'description' => 'Paket premium'],
            ['name' => '50 Mbps', 'speed_mbps' => 50, 'price' => 300000, 'description' => 'Paket super'],
        ];

        foreach ($packages as $pkg) {
            InternetPackage::firstOrCreate(['name' => $pkg['name']], $pkg);
        }
    }
}
