<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Integrasi form laporan (Volt): memastikan save() menyimpan laporan dan
 * benar-benar memanggil SyncReportTechnicians (penugasan + notifikasi).
 */
class LaporanFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_laporan_menugaskan_teknisi_dan_notifikasi(): void
    {
        $admin = User::factory()->admin()->create();
        $type  = DamageType::factory()->create();
        $tech  = User::factory()->teknisi()->create();

        $this->actingAs($admin);

        Volt::test('pages.laporan.index')
            ->set('customer_name', 'Budi')
            ->set('address', 'Jl. Mawar No. 1')
            ->set('damage_type_id', $type->id)
            ->set('selectedTechnicians', [$tech->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_reports', [
            'customer_name' => 'Budi',
            'status'        => 'ditugaskan',
            'created_by'    => $admin->id,
        ]);
        $this->assertDatabaseHas('task_assignments', ['technician_id' => $tech->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $tech->id,
            'title'   => 'Tugas Baru Ditugaskan',
        ]);
    }

    public function test_validasi_menolak_input_kosong(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('pages.laporan.index')
            ->call('save')
            ->assertHasErrors(['customer_name', 'address', 'damage_type_id']);

        $this->assertSame(0, DamageReport::count());
        $this->assertSame(0, TaskAssignment::count());
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

        $this->actingAs($admin);

        try {
            Volt::test('pages.laporan.index')
                ->set('customer_name', 'Atomik')
                ->set('address', 'Jl. Uji No. 9')
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

        $this->actingAs($admin);

        Volt::test('pages.laporan.index')
            ->set('customer_name', 'Citra')
            ->set('address', 'Jl. Anggrek No. 2')
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

        Volt::test('pages.laporan.index')
            ->call('confirmDelete', $report->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingName', 'Pak Sigit'); // nama dipersiapkan saat buka modal
    }
}
