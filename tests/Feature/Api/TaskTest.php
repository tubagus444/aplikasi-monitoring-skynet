<?php

namespace Tests\Feature\Api;

use App\Enums\ReportCategory;
use App\Models\Customer;
use App\Models\CustomerPhoto;
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

    public function test_detail_tugas_pelanggan_menyertakan_kontak_dan_foto_rumah(): void
    {
        $teknisi  = User::factory()->teknisi()->create();
        $pkg = \App\Models\InternetPackage::create(['name' => '20 Mbps', 'speed_mbps' => 20, 'price' => 150000]);
        $customer = Customer::factory()->create([
            'name'                => 'Pak Hendra',
            'phone'               => '081234567890',
            'ip_address'          => '192.168.10.5',
            'internet_package_id' => $pkg->id,
        ]);
        CustomerPhoto::create([
            'customer_id' => $customer->id,
            'uploaded_by' => $teknisi->id,
            'path'        => 'customer-photos/rumah.jpg',
        ]);
        $report = DamageReport::factory()->create([
            'category'      => ReportCategory::Pelanggan->value,
            'customer_id'   => $customer->id,
            'customer_name' => 'Pak Hendra',
        ]);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson("/api/tasks/{$assignment->id}");

        $response->assertOk()
            ->assertJsonPath('data.category', ReportCategory::Pelanggan->value)
            ->assertJsonPath('data.headline', 'Pak Hendra')
            ->assertJsonPath('data.customer_code', $customer->customer_code)
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.ip_address', '192.168.10.5')
            ->assertJsonPath('data.subscription_package', '20 Mbps');

        $this->assertCount(1, $response->json('data.house_photos'));
        $this->assertStringContainsString('customer-photos/rumah.jpg', $response->json('data.house_photos.0'));
    }

    public function test_tugas_non_pelanggan_field_pelanggan_kosong(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->jaringan()->create(['title' => 'Kabel Utama Putus']);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson("/api/tasks/{$assignment->id}");

        $response->assertOk()
            ->assertJsonPath('data.category', ReportCategory::Jaringan->value)
            ->assertJsonPath('data.headline', 'Kabel Utama Putus')
            ->assertJsonPath('data.customer', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.ip_address', null)
            ->assertJsonPath('data.subscription_package', null);

        $this->assertSame([], $response->json('data.house_photos'));
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

    public function test_catatan_pekerjaan_tersimpan_di_work_log(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", [
                'status'      => 'done',
                'description' => 'Ganti konektor RJ45 dan rapikan kabel di ODP.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('work_logs', [
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
            'status'        => 'selesai',
            'description'   => 'Ganti konektor RJ45 dan rapikan kabel di ODP.',
        ]);
    }

    public function test_catatan_pekerjaan_opsional_boleh_kosong(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->create(['status' => 'ditugaskan']);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        // Tanpa field description sama sekali → tetap sukses, description NULL.
        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        $this->assertDatabaseHas('work_logs', [
            'report_id'   => $report->id,
            'status'      => 'sedang_memperbaiki',
            'description' => null,
        ]);
    }

    public function test_catatan_pekerjaan_muncul_di_detail_tugas(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", [
                'status'      => 'done',
                'description' => 'Modem direset dan firmware diperbarui.',
            ])->assertOk();

        $this->actingAs($teknisi, 'sanctum')
            ->getJson("/api/tasks/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.work_logs.0.description', 'Modem direset dan firmware diperbarui.');
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

    public function test_teknisi_dapat_melihat_daftar_riwayat_tugas_selesai(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $reportSelesai = DamageReport::factory()->selesai()->create(['completed_at' => now()]);
        $assignment = TaskAssignment::create([
            'report_id'     => $reportSelesai->id,
            'technician_id' => $teknisi->id,
        ]);

        $response = $this->actingAs($teknisi, 'sanctum')
            ->getJson('/api/tasks?status=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.status', 'selesai');
    }

    public function test_daftar_riwayat_tugas_selesai_tidak_mencakup_tugas_aktif(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $reportAktif = DamageReport::factory()->create(['status' => 'ditugaskan']);
        $reportSelesai = DamageReport::factory()->selesai()->create(['completed_at' => now()]);

        $assignmentAktif = TaskAssignment::create([
            'report_id'     => $reportAktif->id,
            'technician_id' => $teknisi->id,
        ]);
        $assignmentSelesai = TaskAssignment::create([
            'report_id'     => $reportSelesai->id,
            'technician_id' => $teknisi->id,
        ]);

        // Default: hanya tugas aktif
        $resAktif = $this->actingAs($teknisi, 'sanctum')->getJson('/api/tasks');
        $resAktif->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($assignmentAktif->id, $resAktif->json('data.0.id'));

        // Query status=completed: hanya tugas selesai
        $resSelesai = $this->actingAs($teknisi, 'sanctum')->getJson('/api/tasks?status=completed');
        $resSelesai->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($assignmentSelesai->id, $resSelesai->json('data.0.id'));
    }

    public function test_payload_tugas_menyertakan_completed_at(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $waktuSelesai = now();
        $report = DamageReport::factory()->selesai()->create(['completed_at' => $waktuSelesai]);
        $assignment = TaskAssignment::create([
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
        ]);

        $response = $this->actingAs($teknisi, 'sanctum')
            ->getJson("/api/tasks/{$assignment->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.completed_at', $waktuSelesai->toIso8601String());
    }
}
