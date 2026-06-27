<?php

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Models\Customer;
use App\Models\DamageType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DamageReportFactory extends Factory
{
    /**
     * Default = kategori pelanggan: tautkan ke satu Customer & ambil snapshot
     * nama/alamat darinya (mirip alur web admin). Override `customer_name`/`address`
     * secara eksplisit di test akan menang atas closure di bawah.
     */
    public function definition(): array
    {
        return [
            'created_by'     => User::factory()->admin(),
            'damage_type_id' => DamageType::factory(),
            'category'       => ReportCategory::Pelanggan->value,
            'customer_id'    => Customer::factory(),
            'customer_name'  => fn (array $attrs) => Customer::find($attrs['customer_id'])?->name ?? fake()->name(),
            'title'          => null,
            'address'        => fn (array $attrs) => Customer::find($attrs['customer_id'])?->address ?? fake()->address(),
            'notes'          => fake()->optional()->sentence(),
            'status'         => ReportStatus::Ditugaskan->value,
        ];
    }

    /** Laporan tanpa pelanggan (gangguan jaringan/infrastruktur). */
    public function jaringan(): static
    {
        return $this->state(fn () => [
            'category'      => ReportCategory::Jaringan->value,
            'customer_id'   => null,
            'customer_name' => null,
            'title'         => 'Gangguan ' . fake()->randomElement(['kabel utama', 'ODP', 'backbone']) . ' area ' . fake()->city(),
            'address'       => fake()->randomElement(['Cibitung', 'Tambun', 'Cikarang']),
        ]);
    }

    /** Laporan tanpa pelanggan (pemeliharaan/preventif). */
    public function pemeliharaan(): static
    {
        return $this->state(fn () => [
            'category'      => ReportCategory::Pemeliharaan->value,
            'customer_id'   => null,
            'customer_name' => null,
            'title'         => fake()->randomElement(['Perawatan rutin perangkat', 'Upgrade server', 'Pengecekan POP']),
            'address'       => fake()->randomElement(['Kantor SkyNet', 'POP Cibitung', 'POP Tambun']),
        ]);
    }

    public function sedangDiperbaiki(): static
    {
        return $this->state(['status' => ReportStatus::SedangMemperbaiki->value]);
    }

    public function selesai(): static
    {
        return $this->state(fn () => [
            'status'       => ReportStatus::Selesai->value,
            'completed_at' => now(),
        ]);
    }
}
