<?php

namespace Database\Factories;

use App\Enums\IpPoolStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpPoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ip_address'  => fake()->unique()->ipv4(),
            'segment'     => fake()->randomElement(['Cluster Cibitung', 'Subnet Tambun', '192.168.10.0/24']),
            'status'      => IpPoolStatus::Tersedia->value,
            'customer_id' => null,
            'notes'       => null,
        ];
    }

    public function terpakai(?Customer $customer = null): static
    {
        return $this->state(fn () => [
            'status'      => IpPoolStatus::Terpakai->value,
            'customer_id' => $customer?->id ?? Customer::factory(),
        ]);
    }

    public function reserved(?string $notes = 'Gateway / Infrastructure'): static
    {
        return $this->state(fn () => [
            'status'      => IpPoolStatus::Reserved->value,
            'customer_id' => null,
            'notes'       => $notes,
        ]);
    }
}
