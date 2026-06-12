<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Regresi: saat search + filter status/role dipakai bersamaan, kondisi search
 * (yang memakai orWhere) HARUS dikelompokkan agar filter tidak bocor.
 *
 * SQL yang benar: (cocok_search) AND status = X
 * Bug sebelumnya:  cocok_kolom_A OR (cocok_kolom_B AND status = X)
 * -> baris dengan status berbeda ikut muncul asal nama/judulnya cocok search.
 */
class FilterScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_status_laporan_tidak_bocor_saat_digabung_dengan_search(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();

        // Dua laporan dengan nama yang sama-sama cocok search 'Pelanggan',
        // tapi status berbeda. Alamat sengaja tanpa kata 'Pelanggan'.
        DamageReport::factory()->sedangDiperbaiki()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'PelangganAlpha',
            'address'        => 'Jl. Mawar No. 1',
        ]);
        DamageReport::factory()->selesai()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'PelangganOmega',
            'address'        => 'Jl. Melati No. 2',
        ]);

        $this->actingAs($admin);

        Volt::test('pages.laporan.index')
            ->set('search', 'Pelanggan')
            ->set('filterStatus', 'sedang_memperbaiki')
            ->assertSee('PelangganAlpha')      // status cocok -> tampil
            ->assertDontSee('PelangganOmega');  // status beda -> TIDAK boleh bocor
    }

    public function test_filter_role_pengguna_tidak_bocor_saat_digabung_dengan_search(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->teknisi()->create(['name' => 'PenggunaAlpha']);
        User::factory()->admin()->create(['name' => 'PenggunaOmega']);

        $this->actingAs($admin);

        Volt::test('pages.pengguna.index')
            ->set('search', 'Pengguna')
            ->set('filterRole', 'teknisi')
            ->assertSee('PenggunaAlpha')       // role cocok -> tampil
            ->assertDontSee('PenggunaOmega');   // role beda -> TIDAK boleh bocor
    }
}
