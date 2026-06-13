<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin SkyNet',
            'email'    => 'admin@skynet.test',
            'password' => Hash::make('password'),
            'role'     => UserRole::Admin->value,
        ]);

        User::create([
            'name'     => 'Budi Teknisi',
            'email'    => 'teknisi1@skynet.test',
            'password' => Hash::make('password'),
            'role'     => UserRole::Teknisi->value,
        ]);

        User::create([
            'name'     => 'Andi Teknisi',
            'email'    => 'teknisi2@skynet.test',
            'password' => Hash::make('password'),
            'role'     => UserRole::Teknisi->value,
        ]);

        User::create([
            'name'     => 'Riko Teknisi',
            'email'    => 'teknisi3@skynet.test',
            'password' => Hash::make('password'),
            'role'     => UserRole::Teknisi->value,
        ]);
    }
}