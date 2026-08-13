<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * CRUD halaman admin Paket Internet (master data): buat/edit/hapus + nama unik,
 * dan regresi penting — menghapus paket yang dipakai pelanggan TIDAK menghapus
 * pelanggannya (FK `nullOnDelete`), kolom internet_package_id jadi NULL.
 */
class PaketInternetCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_dapat_menambah_paket(): void
    {
        Volt::test('pages.paket-internet.index')
            ->set('name', '100 Mbps')
            ->set('speed_mbps', '100')
            ->set('price', '500000')
            ->set('description', 'Paket super cepat')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('internet_packages', [
            'name'       => '100 Mbps',
            'speed_mbps' => 100,
            'price'      => 500000,
        ]);
    }

    public function test_admin_dapat_mengedit_paket(): void
    {
        $pkg = InternetPackage::create([
            'name' => '10 Mbps', 'speed_mbps' => 10, 'price' => 100000,
        ]);

        Volt::test('pages.paket-internet.index')
            ->call('openEdit', $pkg->id)
            ->assertSet('name', '10 Mbps')
            ->set('price', '120000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('internet_packages', [
            'id'    => $pkg->id,
            'price' => 120000,
        ]);
    }

    public function test_nama_wajib_dan_unik(): void
    {
        InternetPackage::create([
            'name' => '20 Mbps', 'speed_mbps' => 20, 'price' => 150000,
        ]);

        // Nama kosong → required
        Volt::test('pages.paket-internet.index')
            ->set('name', '')
            ->set('speed_mbps', '20')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        // Nama duplikat → unique
        Volt::test('pages.paket-internet.index')
            ->set('name', '20 Mbps')
            ->set('speed_mbps', '20')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, InternetPackage::count());
    }

    public function test_edit_boleh_simpan_nama_sendiri_tanpa_bentrok_unik(): void
    {
        $pkg = InternetPackage::create([
            'name' => '30 Mbps', 'speed_mbps' => 30, 'price' => 200000,
        ]);

        Volt::test('pages.paket-internet.index')
            ->call('openEdit', $pkg->id)
            ->set('description', 'Paket premium')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('internet_packages', [
            'id'          => $pkg->id,
            'description' => 'Paket premium',
        ]);
    }

    public function test_hapus_paket_menjaga_pelanggan_dan_mengosongkan_kolom(): void
    {
        $pkg = InternetPackage::create([
            'name' => '50 Mbps', 'speed_mbps' => 50, 'price' => 300000,
        ]);
        $customer = Customer::factory()->create([
            'internet_package_id' => $pkg->id,
        ]);

        Volt::test('pages.paket-internet.index')
            ->call('confirmDelete', $pkg->id)
            ->assertSet('deletingName', '50 Mbps')
            ->assertSet('deletingUsage', 1)
            ->call('deletePackage')
            ->assertHasNoErrors();

        // Paket terhapus, tapi pelanggan TETAP ADA dengan internet_package_id = NULL.
        $this->assertDatabaseMissing('internet_packages', ['id' => $pkg->id]);
        $this->assertDatabaseHas('customers', [
            'id'                  => $customer->id,
            'internet_package_id' => null,
        ]);
    }

    public function test_harga_opsional_disimpan_null(): void
    {
        Volt::test('pages.paket-internet.index')
            ->set('name', '5 Mbps')
            ->set('speed_mbps', '5')
            ->set('price', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('internet_packages', [
            'name'  => '5 Mbps',
            'price' => null,
        ]);
    }

    public function test_kecepatan_wajib_dan_harus_positif(): void
    {
        Volt::test('pages.paket-internet.index')
            ->set('name', 'Test')
            ->set('speed_mbps', '0')
            ->call('save')
            ->assertHasErrors(['speed_mbps']);

        Volt::test('pages.paket-internet.index')
            ->set('name', 'Test')
            ->set('speed_mbps', '')
            ->call('save')
            ->assertHasErrors(['speed_mbps']);
    }
}
