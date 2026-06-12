<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: menghapus pengguna TIDAK boleh ikut menghapus riwayat/jejak audit.
 *
 * Dulu semua FK dari `users` memakai cascadeOnDelete — menghapus seorang teknisi
 * ikut menghapus work log-nya, dan menghapus admin pembuat ikut menghapus laporan
 * yang ia buat. Riwayat selesai (halaman Riwayat + PDF) kehilangan data diam-diam.
 *
 * Sekarang `damage_reports.created_by` & `work_logs.technician_id` = nullOnDelete:
 * baris histori tetap ada, kolom pengguna-nya jadi NULL. `task_assignments` &
 * `location_logs` sengaja tetap cascade (penanda live, bukan arsip).
 */
class UserDeletionPreservesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_hapus_teknisi_menyisakan_work_log_dengan_teknisi_null(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->selesai()->create();

        $log = WorkLog::create([
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
            'status'        => 'selesai',
        ]);

        $teknisi->delete();

        // Jejak audit tetap ada — hanya identitas teknisinya yang dilepas.
        $this->assertDatabaseHas('work_logs', [
            'id'            => $log->id,
            'technician_id' => null,
        ]);
    }

    public function test_hapus_admin_pembuat_menyisakan_laporan_dengan_created_by_null(): void
    {
        $admin  = User::factory()->admin()->create();
        $report = DamageReport::factory()->selesai()->create(['created_by' => $admin->id]);

        $admin->delete();

        // Laporan (termasuk riwayat selesai) tetap ada — penulisnya saja yang hilang.
        $this->assertDatabaseHas('damage_reports', [
            'id'         => $report->id,
            'created_by' => null,
        ]);
    }

    public function test_hapus_teknisi_tetap_melepas_penugasan_aktif_secara_cascade(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->create();
        TaskAssignment::create([
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
        ]);

        $teknisi->delete();

        // Penugasan (penanda "sedang ditugaskan") ikut terhapus — ini disengaja...
        $this->assertDatabaseMissing('task_assignments', ['technician_id' => $teknisi->id]);
        // ...tetapi laporannya sendiri tidak ikut terhapus.
        $this->assertDatabaseHas('damage_reports', ['id' => $report->id]);
    }
}
