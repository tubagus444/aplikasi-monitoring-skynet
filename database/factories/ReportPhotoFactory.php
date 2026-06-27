<?php

namespace Database\Factories;

use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id'   => DamageReport::factory(),
            'uploaded_by' => User::factory()->teknisi(),
            // Path placeholder — file fisik tidak dibuat oleh factory.
            'path'        => 'report-photos/' . fake()->uuid() . '.jpg',
            'caption'     => fake()->optional()->randomElement([
                'Kondisi sebelum', 'Kondisi sesudah', 'Konektor diganti', 'Kabel dirapikan',
            ]),
        ];
    }
}
