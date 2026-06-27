<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Mayoritas aktif + sebagian isolir/berhenti agar filter status ada isinya.
        Customer::factory()->count(12)->create();
        Customer::factory()->count(3)->isolir()->create();
        Customer::factory()->count(2)->berhenti()->create();
    }
}
