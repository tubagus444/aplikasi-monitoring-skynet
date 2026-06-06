<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin SkyNet',
            'email'    => 'admin@skynet.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        User::create([
            'name'     => 'Budi Teknisi',
            'email'    => 'teknisi1@skynet.test',
            'password' => Hash::make('password'),
            'role'     => 'teknisi',
        ]);

        User::create([
            'name'     => 'Andi Teknisi',
            'email'    => 'teknisi2@skynet.test',
            'password' => Hash::make('password'),
            'role'     => 'teknisi',
        ]);

        User::create([
            'name'     => 'Riko Teknisi',
            'email'    => 'teknisi3@skynet.test',
            'password' => Hash::make('password'),
            'role'     => 'teknisi',
        ]);
    }
}