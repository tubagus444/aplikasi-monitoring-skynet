<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'                 => fake()->name(),
            'phone'                => fake()->numerify('08##########'),
            'address'              => fake()->streetAddress() . ', ' . fake()->randomElement(['Cibitung', 'Tambun', 'Cikarang']),
            'ip_address'           => fake()->optional()->ipv4(),
            'subscription_package' => fake()->optional()->randomElement(['10 Mbps', '20 Mbps', '30 Mbps', '50 Mbps']),
            'status'               => CustomerStatus::Aktif->value,
            // Sekitar Kab. Bekasi; nullable → sebagian sengaja dikosongkan.
            'latitude'             => fake()->optional()->randomFloat(8, -6.40, -6.20),
            'longitude'            => fake()->optional()->randomFloat(8, 107.00, 107.20),
            'installed_at'         => fake()->optional()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public function isolir(): static
    {
        return $this->state(['status' => CustomerStatus::Isolir->value]);
    }

    public function berhenti(): static
    {
        return $this->state(['status' => CustomerStatus::Berhenti->value]);
    }
}
