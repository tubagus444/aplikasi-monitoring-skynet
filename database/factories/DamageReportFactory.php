<?php

namespace Database\Factories;

use App\Models\DamageType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DamageReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by'     => User::factory()->admin(),
            'damage_type_id' => DamageType::factory(),
            'customer_name'  => fake()->name(),
            'address'        => fake()->address(),
            'notes'          => fake()->optional()->sentence(),
            'status'         => 'ditugaskan',
        ];
    }

    public function sedangDiperbaiki(): static
    {
        return $this->state(['status' => 'sedang_memperbaiki']);
    }

    public function selesai(): static
    {
        return $this->state(['status' => 'selesai']);
    }
}
