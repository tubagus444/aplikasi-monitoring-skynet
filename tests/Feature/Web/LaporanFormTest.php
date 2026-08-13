<?php

namespace Tests\Feature\Web;

use App\Enums\ReportCategory;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Integrasi form laporan (Volt): memastikan save() menyimpan laporan dan
 * benar-benar memanggil SyncReportTechnicians (penugasan + notifikasi), serta
 * validasi bersyarat per kategori (pelanggan vs jaringan/pemeliharaan).
 */
class LaporanFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_laporan_pelanggan_snapshot_dan_menugaskan_teknisi(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();
        $tech  = User::factory()->teknisi()->create();
        $customer = Customer::factory()->create(['name' => 'Budi', 'address' => 'Jl. Mawar No. 1']);

        $this->actingAs($admin);

        Volt::test('pages.laporan.aktif')
            ->set('customer_id', $customer->id) // kategori default = pelanggan (dari mount)
            ->set('damage_type_id', $type->id)
            ->set('selectedTechnicians', [$tech->id])
            ->call('save')
            ->assertHasNoErrors();

        // Snapshot nama/alamat diambil dari pelanggan terpilih.
        $this->assertDatabaseHas('damage_reports', [
            'category'      => ReportCategory::Pelanggan->value,
            'customer_id'   => $customer->id,
            'customer_name' => 'Budi',
            'address'       => 'Jl. Mawar No. 1',
            'title'         => null,
            'status'        => 'ditugaskan',
            'created_by'    => $admin->id,
        ]);
        $this->assertDatabaseHas('task_assignments', ['technician_id' => $tech->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $tech->id,
            'title'   => 'Tugas Baru Ditugaskan',
        ]);
    }

    public function test_membuat_laporan_jaringan_tanpa_pelanggan(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();
        $this->actingAs($admin);

        Volt::test('pages.laporan.aktif')
            ->set('category', ReportCategory::Jaringan->value)
            ->set('title', 'Kabel utama putus area Cibitung')
            ->set('address', 'Backbone RT 03, Cibitung')
            ->set('damage_type_id', $type->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_reports', [
            'category'      => ReportCategory::Jaringan->value,
            'customer_id'   => null,
            'customer_name' => null,
            'title'         => 'Kabel utama putus area Cibitung',
            'address'       => 'Backbone RT 03, Cibitung',
        ]);
    }

    public function test_laporan_non_pelanggan_boleh_tanpa_jenis_gangguan(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Kategori pemeliharaan: jenis gangguan opsional (pekerjaan preventif, bukan
        // kerusakan). Tanpa damage_type_id pun harus lolos validasi & tersimpan null.
        Volt::test('pages.laporan.aktif')
            ->set('category', ReportCategory::Pemeliharaan->value)
            ->set('title', 'Perawatan rutin POP Cibitung')
            ->set('address', 'POP Cibitung')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_reports', [
            'category'       => ReportCategory::Pemeliharaan->value,
            'title'          => 'Perawatan rutin POP Cibitung',
            'damage_type_id' => null,
        ]);
    }

    public function test_laporan_pelanggan_tetap_wajib_jenis_gangguan(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $customer = Customer::factory()->create();

        // Kategori pelanggan (default): jenis gangguan tetap wajib.
        Volt::test('pages.laporan.aktif')
            ->set('customer_id', $customer->id)
            ->call('save')
            ->assertHasErrors(['damage_type_id' => 'required']);

        $this->assertSame(0, DamageReport::count());
    }

    public function test_validasi_pelanggan_butuh_customer_id(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Kategori default pelanggan → customer_id wajib; damage_type wajib.
        Volt::test('pages.laporan.aktif')
            ->call('save')
            ->assertHasErrors(['customer_id', 'damage_type_id']);

        $this->assertSame(0, DamageReport::count());
        $this->assertSame(0, TaskAssignment::count());
    }

    public function test_validasi_jaringan_butuh_judul_dan_lokasi(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();
        $this->actingAs($admin);

        Volt::test('pages.laporan.aktif')
            ->set('category', ReportCategory::Jaringan->value)
            ->set('damage_type_id', $type->id)
            ->call('save')
            ->assertHasErrors(['title', 'address']);

        $this->assertSame(0, DamageReport::count());
    }

    /**
     * Atomicity: bila sinkron penugasan gagal di tengah jalan, laporan tidak boleh
     * tertinggal dalam keadaan setengah jadi. Teknisi id tak dikenal memicu FK
     * violation saat membuat penugasan; transaksi di save() harus me-rollback.
     */
    public function test_save_membatalkan_laporan_saat_sinkron_penugasan_gagal(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin);

        try {
            Volt::test('pages.laporan.aktif')
                ->set('customer_id', $customer->id)
                ->set('damage_type_id', $type->id)
                ->set('selectedTechnicians', [999999]) // teknisi tidak ada → FK gagal
                ->call('save');

            $this->fail('save() seharusnya melempar karena penugasan gagal');
        } catch (\Throwable $e) {
            // diharapkan — insert penugasan menabrak foreign key
        }

        // Rollback total: tidak ada laporan maupun penugasan tertinggal.
        $this->assertSame(0, DamageReport::count());
        $this->assertSame(0, TaskAssignment::count());
    }

    /**
     * Hardening: selectedTechnicians bisa dimanipulasi dari browser. ID yang bukan
     * teknisi (mis. admin) atau ID tak dikenal harus ditolak validasi — bukan lolos
     * jadi penugasan, dan bukan error 500 dari FK violation.
     */
    public function test_validasi_menolak_teknisi_palsu_atau_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin);

        Volt::test('pages.laporan.aktif')
            ->set('customer_id', $customer->id)
            ->set('damage_type_id', $type->id)
            ->set('selectedTechnicians', [$admin->id, 999999]) // admin + ID tak ada
            ->call('save')
            ->assertHasErrors('selectedTechnicians.0')  // admin: bukan role teknisi
            ->assertHasErrors('selectedTechnicians.1'); // ID tak dikenal

        $this->assertSame(0, DamageReport::count());
        $this->assertSame(0, TaskAssignment::count());
    }

    public function test_confirm_delete_menyimpan_nama_tanpa_query_di_view(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $report = DamageReport::factory()->create(['customer_name' => 'Pak Sigit']);

        Volt::test('pages.laporan.aktif')
            ->call('confirmDelete', $report->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingName', 'Pak Sigit'); // judul (customer_name ?? title)
    }
}
