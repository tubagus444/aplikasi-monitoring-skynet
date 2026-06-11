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
}
