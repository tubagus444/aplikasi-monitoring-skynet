<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'uploaded_by' => User::factory()->admin(),
            // Path placeholder — file fisik tidak dibuat oleh factory.
            'path'        => 'customer-photos/' . fake()->uuid() . '.jpg',
            'caption'     => fake()->optional()->randomElement([
                'Tampak depan', 'Patokan gang', 'Warna pagar', 'Lokasi tiang',
            ]),
        ];
    }
}
