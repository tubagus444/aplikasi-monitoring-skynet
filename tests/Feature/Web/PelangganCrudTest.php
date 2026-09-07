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
 *
 * Termasuk test soft delete: restore, force delete, dan tampilan terhapus.
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
            'customer_code'       => 'SKY-0001',
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

    // ── Soft Delete ─────────────────────────────────────────────────────

    public function test_hapus_pelanggan_soft_delete(): void
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

        // Pelanggan soft-deleted — baris masih ada di DB, tapi punya deleted_at.
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        // Laporan tetap ada; customer_id TETAP terisi (bukan NULL) karena
        // pelanggan masih ada di database (hanya soft-deleted).
        $this->assertDatabaseHas('damage_reports', [
            'id'            => $report->id,
            'customer_id'   => $customer->id,
            'customer_name' => 'Snapshot Nama',
        ]);
    }

    public function test_restore_pelanggan(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete(); // soft delete

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        Volt::test('pages.pelanggan.index')
            ->call('restoreCustomer', $customer->id);

        // Setelah restore, pelanggan kembali aktif (deleted_at = null).
        $this->assertDatabaseHas('customers', [
            'id'         => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_pelanggan_hapus_permanen(): void
    {
        $customer = Customer::factory()->create();
        $report = DamageReport::factory()->create([
            'customer_id'   => $customer->id,
            'customer_name' => 'Snapshot Permanen',
        ]);

        $customer->delete(); // soft delete dulu

        Volt::test('pages.pelanggan.index')
            ->call('confirmForceDelete', $customer->id)
            ->assertSet('deletingName', $customer->name)
            ->call('forceDeleteCustomer')
            ->assertSet('showForceDeleteModal', false);

        // Pelanggan benar-benar hilang dari database.
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);

        // Laporan tetap ada; FK jadi NULL (nullOnDelete), snapshot tetap utuh.
        $this->assertDatabaseHas('damage_reports', [
            'id'            => $report->id,
            'customer_id'   => null,
            'customer_name' => 'Snapshot Permanen',
        ]);
    }

    public function test_toggle_tampilan_terhapus(): void
    {
        $aktif = Customer::factory()->create(['name' => 'Pelanggan Aktif']);
        $terhapus = Customer::factory()->create(['name' => 'Pelanggan Terhapus']);
        $terhapus->delete(); // soft delete

        // Tampilan normal: hanya pelanggan aktif
        $component = Volt::test('pages.pelanggan.index');
        $rows = $component->get('customers');
        $this->assertCount(1, $rows);
        $this->assertSame('Pelanggan Aktif', $rows->first()->name);

        // Toggle ke tampilan terhapus: hanya pelanggan soft-deleted
        $component->call('toggleTrashed')->assertSet('showTrashed', true);
        $rows = $component->get('customers');
        $this->assertCount(1, $rows);
        $this->assertSame('Pelanggan Terhapus', $rows->first()->name);
    }

    public function test_trashed_count_badge(): void
    {
        Customer::factory()->create(); // aktif
        $terhapus1 = Customer::factory()->create();
        $terhapus2 = Customer::factory()->create();
        $terhapus1->delete();
        $terhapus2->delete();

        $component = Volt::test('pages.pelanggan.index');
        $this->assertSame(2, $component->get('trashedCount'));
    }

    // ── Filter ──────────────────────────────────────────────────────────

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

    public function test_soft_deleted_tidak_muncul_di_daftar_utama(): void
    {
        $aktif = Customer::factory()->create(['name' => 'Tampil']);
        $terhapus = Customer::factory()->create(['name' => 'Tersembunyi']);
        $terhapus->delete();

        $rows = Volt::test('pages.pelanggan.index')->get('customers');
        $this->assertCount(1, $rows);
        $this->assertSame('Tampil', $rows->first()->name);
    }

    public function test_customer_code_otomatis_terbuat_dan_tidak_duplikasi_saat_soft_delete(): void
    {
        $c1 = Customer::factory()->create();
        $this->assertSame('SKY-0001', $c1->customer_code);

        $c2 = Customer::factory()->create();
        $this->assertSame('SKY-0002', $c2->customer_code);

        // Hapus soft delete c2
        $c2->delete();

        // Buat pelanggan ke-3, harus SKY-0003 bukan menimpa SKY-0002
        $c3 = Customer::factory()->create();
        $this->assertSame('SKY-0003', $c3->customer_code);
    }

    public function test_pencarian_dengan_customer_code(): void
    {
        Customer::factory()->create(['name' => 'Budi', 'customer_code' => 'SKY-0010']);
        Customer::factory()->create(['name' => 'Andi', 'customer_code' => 'SKY-0011']);

        $rows = Volt::test('pages.pelanggan.index')
            ->set('search', 'SKY-0010')
            ->get('customers');

        $this->assertCount(1, $rows);
        $this->assertSame('Budi', $rows->first()->name);
        $this->assertSame('SKY-0010', $rows->first()->customer_code);
    }
}
