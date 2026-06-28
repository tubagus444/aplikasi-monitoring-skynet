<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Modul CRUD Jenis Gangguan/Pekerjaan: buat/edit/hapus + nama unik, dan regresi
 * penting — menghapus jenis yang dipakai laporan TIDAK menghapus laporannya
 * (FK `nullOnDelete`), kolomnya hanya jadi NULL. Selaras prinsip arsip app.
 */
class JenisGangguanCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_menambah_jenis_gangguan(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('pages.jenis-gangguan.index')
            ->set('name', 'Konektor Rusak')
            ->set('description', 'Konektor fiber longgar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_types', [
            'name'        => 'Konektor Rusak',
            'description' => 'Konektor fiber longgar',
        ]);
    }

    public function test_admin_dapat_mengedit_jenis_gangguan(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $type = DamageType::create(['name' => 'Kabel Putus']);

        Volt::test('pages.jenis-gangguan.index')
            ->call('openEdit', $type->id)
            ->assertSet('name', 'Kabel Putus')
            ->set('name', 'Kabel Putus Total')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_types', ['id' => $type->id, 'name' => 'Kabel Putus Total']);
        // updated_at terisi saat diedit (timestamps aktif sejak ada menu CRUD).
        $this->assertNotNull($type->fresh()->updated_at);
    }

    public function test_nama_wajib_dan_unik(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        DamageType::create(['name' => 'Router Bermasalah']);

        // Nama kosong → required
        Volt::test('pages.jenis-gangguan.index')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);

        // Nama duplikat → unique
        Volt::test('pages.jenis-gangguan.index')
            ->set('name', 'Router Bermasalah')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        $this->assertSame(1, DamageType::count());
    }

    public function test_edit_boleh_simpan_nama_sendiri_tanpa_bentrok_unik(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $type = DamageType::create(['name' => 'Internet Lambat']);

        Volt::test('pages.jenis-gangguan.index')
            ->call('openEdit', $type->id)
            ->set('description', 'Di bawah paket')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('damage_types', ['id' => $type->id, 'description' => 'Di bawah paket']);
    }

    public function test_hapus_jenis_menjaga_laporan_dan_mengosongkan_kolom(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $type   = DamageType::create(['name' => 'Access Point Mati']);
        $report = DamageReport::factory()->create(['damage_type_id' => $type->id]);

        Volt::test('pages.jenis-gangguan.index')
            ->call('confirmDelete', $type->id)
            ->assertSet('deletingName', 'Access Point Mati')
            ->assertSet('deletingUsage', 1)
            ->call('deleteType')
            ->assertHasNoErrors();

        // Jenis terhapus, tapi laporan TETAP ADA dengan damage_type_id = NULL.
        $this->assertDatabaseMissing('damage_types', ['id' => $type->id]);
        $this->assertDatabaseHas('damage_reports', [
            'id'             => $report->id,
            'damage_type_id' => null,
        ]);
    }
}
