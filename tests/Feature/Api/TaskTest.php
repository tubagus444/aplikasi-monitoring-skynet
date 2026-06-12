<?php

namespace Tests\Feature\Api;

use App\Models\DamageReport;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_teknisi_dapat_melihat_daftar_tugas(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->create();
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson('/api/tasks');

        $response->assertOk()->assertJsonStructure([
            'data' => [['id', 'report_id', 'status', 'customer', 'address', 'damage_type']],
        ]);
    }

    public function test_teknisi_hanya_melihat_tugas_miliknya(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $lain    = User::factory()->teknisi()->create();

        $reportSaya  = DamageReport::factory()->create();
        $reportLain  = DamageReport::factory()->create();

        TaskAssignment::create(['report_id' => $reportSaya->id, 'technician_id' => $teknisi->id]);
        TaskAssignment::create(['report_id' => $reportLain->id, 'technician_id' => $lain->id]);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson('/api/tasks');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($reportSaya->id, $response->json('data.0.report_id'));
    }

    public function test_teknisi_dapat_melihat_detail_tugas(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $response = $this->actingAs($teknisi, 'sanctum')
            ->getJson("/api/tasks/{$assignment->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'status', 'customer', 'work_logs']]);
    }

    public function test_teknisi_dapat_update_status_ke_sedang_memperbaiki(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->create(['status' => 'ditugaskan']);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJson(['status' => 'sedang_memperbaiki']);

        $this->assertDatabaseHas('damage_reports', [
            'id'     => $report->id,
            'status' => 'sedang_memperbaiki',
        ]);
    }

    public function test_teknisi_dapat_update_status_ke_selesai(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJson(['status' => 'selesai']);

        $this->assertDatabaseHas('damage_reports', [
            'id'     => $report->id,
            'status' => 'selesai',
        ]);
    }

    public function test_update_status_ditolak_jika_transisi_tidak_valid(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->create(['status' => 'ditugaskan']);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        // Loncat dari ditugaskan langsung ke selesai tidak diizinkan
        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'done'])
            ->assertUnprocessable();
    }

    public function test_work_log_dibuat_setelah_update_status(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->create(['status' => 'ditugaskan']);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress']);

        $this->assertDatabaseHas('work_logs', [
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
            'status'        => 'sedang_memperbaiki',
        ]);
    }

    public function test_teknisi_kedua_mulai_saat_laporan_sudah_diperbaiki_bersifat_idempotent(): void
    {
        // Status milik bersama: teknisi A sudah memulai (laporan sedang_memperbaiki),
        // teknisi B yang ditugaskan ke laporan sama tidak boleh ditolak 422.
        $teknisiB   = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisiB->id]);

        $this->actingAs($teknisiB, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJson(['status' => 'sedang_memperbaiki']);

        // Tidak membuat work log ganda untuk transisi yang sudah terjadi
        $this->assertDatabaseMissing('work_logs', ['technician_id' => $teknisiB->id]);
    }

    public function test_menyelesaikan_laporan_mengisi_completed_at(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->assertNull($report->completed_at); // belum selesai → belum ada waktu selesai

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'done'])
            ->assertOk();

        $this->assertNotNull($report->refresh()->completed_at);
    }

    public function test_mulai_memperbaiki_tidak_mengisi_completed_at(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->create(['status' => 'ditugaskan']);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        $this->assertNull($report->refresh()->completed_at);
    }

    public function test_tandai_selesai_saat_laporan_sudah_selesai_bersifat_idempotent(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->selesai()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJson(['status' => 'selesai']);
    }
}
