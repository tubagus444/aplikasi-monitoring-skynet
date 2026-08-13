<?php

namespace Tests\Feature\Web;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\InternetPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * CRUD halaman admin Pelanggan (modul Pelanggan, batch 3): tambah/edit/hapus,
 * normalisasi field opsional, dan jaminan hapus pelanggan TIDAK menghapus laporan
 * (FK nullOnDelete) — snapshot nama/alamat di laporan tetap utuh.
 */
class PelangganCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_tambah_pelanggan_baru(): void
    {
        $pkg = InternetPackage::create(['name' => '20 Mbps', 'speed_mbps' => 20, 'price' => 150000]);

        Volt::test('pages.pelanggan.index')
            ->set('name', 'Pak Hendra')
            ->set('phone', '081234567890')
            ->set('address', 'Jl. Mawar No. 5, Cibitung')
            ->set('ip_address', '192.168.10.5')
            ->set('internet_package_id', $pkg->id)
            ->set('status', CustomerStatus::Aktif->value)
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'name'                => 'Pak Hendra',
            'phone'               => '081234567890',
            'ip_address'          => '192.168.10.5',
            'internet_package_id' => $pkg->id,
            'status'              => CustomerStatus::Aktif->value,
        ]);
    }

    public function test_field_opsional_kosong_disimpan_null(): void
    {
        Volt::test('pages.pelanggan.index')
            ->set('name', 'Bu Sari')
            ->set('phone', '08123')
            ->set('address', 'Jl. Melati')
            ->set('ip_address', '')
            ->set('internet_package_id', null)
            ->set('latitude', '')
            ->set('longitude', '')
            ->set('installed_at', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'name'                => 'Bu Sari',
            'ip_address'          => null,
            'internet_package_id' => null,
            'latitude'            => null,
            'longitude'           => null,
            'installed_at'        => null,
        ]);
    }

    public function test_validasi_wajib(): void
    {
        Volt::test('pages.pelanggan.index')
            ->set('name', '')
            ->set('phone', '')
            ->set('address', '')
            ->call('save')
            ->assertHasErrors(['name', 'phone', 'address']);
    }

    public function test_validasi_latitude_di_luar_rentang(): void
    {
        Volt::test('pages.pelanggan.index')
            ->set('name', 'X')
            ->set('phone', '08')
            ->set('address', 'Y')
            ->set('latitude', '200')
            ->call('save')
            ->assertHasErrors(['latitude']);
    }

    public function test_edit_pelanggan(): void
    {
        $customer = Customer::factory()->create(['name' => 'Lama', 'status' => CustomerStatus::Aktif->value]);

        Volt::test('pages.pelanggan.index')
            ->call('openEdit', $customer->id)
            ->assertSet('name', 'Lama')
            ->set('name', 'Baru')
            ->set('status', CustomerStatus::Isolir->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'id'     => $customer->id,
            'name'   => 'Baru',
            'status' => CustomerStatus::Isolir->value,
        ]);
    }

    public function test_hapus_pelanggan_tidak_menghapus_laporan(): void
    {
        $customer = Customer::factory()->create();
        $report = DamageReport::factory()->create([
            'customer_id'   => $customer->id,
            'customer_name' => 'Snapshot Nama',
        ]);

        Volt::test('pages.pelanggan.index')
            ->call('confirmDelete', $customer->id)
            ->assertSet('deletingName', $customer->name)
            ->call('deleteCustomer')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        // Laporan tetap ada; FK jadi NULL, snapshot nama tetap utuh.
        $this->assertDatabaseHas('damage_reports', [
            'id'            => $report->id,
            'customer_id'   => null,
            'customer_name' => 'Snapshot Nama',
        ]);
    }

    public function test_filter_status_dan_search(): void
    {
        Customer::factory()->create(['name' => 'Aktif Satu', 'status' => CustomerStatus::Aktif->value]);
        Customer::factory()->create(['name' => 'Isolir Satu', 'status' => CustomerStatus::Isolir->value]);

        $component = Volt::test('pages.pelanggan.index')
            ->set('filterStatus', CustomerStatus::Isolir->value);

        $rows = $component->get('customers');
        $this->assertCount(1, $rows);
        $this->assertSame('Isolir Satu', $rows->first()->name);

        // Search nama
        $component->set('filterStatus', '')->set('search', 'Aktif');
        $rows = $component->get('customers');
        $this->assertCount(1, $rows);
        $this->assertSame('Aktif Satu', $rows->first()->name);
    }
}
