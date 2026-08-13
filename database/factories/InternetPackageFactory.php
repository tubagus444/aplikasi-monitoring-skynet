<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InternetPackageFactory extends Factory
{
    public function definition(): array
    {
        $speed = fake()->randomElement([10, 20, 30, 50]);

        return [
            'name'        => "{$speed} Mbps",
            'speed_mbps'  => $speed,
            'price'       => $speed * 10000,
            'description' => null,
        ];
    }
}
