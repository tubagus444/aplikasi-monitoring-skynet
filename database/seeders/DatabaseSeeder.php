<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DamageTypeSeeder::class,
            InternetPackageSeeder::class,
            CustomerSeeder::class,
            DamageReportSeeder::class,
            TaskAssignmentSeeder::class,
            WorkLogSeeder::class,
            LocationLogSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}