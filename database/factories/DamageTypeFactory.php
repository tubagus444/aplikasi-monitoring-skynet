<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DamageTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->randomElement(['Kabel Putus', 'Router Down', 'Gangguan Sinyal', 'Port Error']),
            'description' => fake()->sentence(),
        ];
    }
}
