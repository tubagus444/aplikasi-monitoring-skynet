<?php

namespace Tests\Feature\Api;

use App\Enums\NotificationType;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\DamageReport;
use App\Models\Notification;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function setupTugasAktif(): array
    {
        $admin   = User::factory()->admin()->create();
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->create([
            'status' => ReportStatus::Ditugaskan->value,
        ]);
        $assignment = TaskAssignment::create([
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
        ]);

        return [$admin, $teknisi, $report, $assignment];
    }

    public function test_admin_mendapat_notifikasi_saat_teknisi_mulai_mengerjakan(): void
    {
        [$admin, $teknisi, $report, $assignment] = $this->setupTugasAktif();

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        // Admin harus mendapat notifikasi task_in_progress
        $notif = Notification::where('user_id', $admin->id)->first();
        $this->assertNotNull($notif);
        $this->assertEquals(NotificationType::TaskInProgress->value, $notif->type);
        $this->assertEquals($report->id, $notif->related_id);
        $this->assertStringContains($teknisi->name, $notif->body);
    }

    public function test_admin_mendapat_notifikasi_saat_teknisi_menyelesaikan_tugas(): void
    {
        [$admin, $teknisi, $report, $assignment] = $this->setupTugasAktif();

        // Pindah ke sedang_memperbaiki dulu
        $report->update(['status' => ReportStatus::SedangMemperbaiki->value]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'done'])
            ->assertOk();

        // Admin harus mendapat notifikasi task_completed
        $notif = Notification::where('user_id', $admin->id)
            ->where('type', NotificationType::TaskCompleted->value)
            ->first();
        $this->assertNotNull($notif);
        $this->assertEquals($report->id, $notif->related_id);
        $this->assertStringContains($teknisi->name, $notif->body);
    }

    public function test_idempotent_status_tidak_membuat_notifikasi_admin_ganda(): void
    {
        [$admin, $teknisi, $report, $assignment] = $this->setupTugasAktif();

        // Status sudah sedang_memperbaiki — request in_progress jadi idempotent
        $report->update(['status' => ReportStatus::SedangMemperbaiki->value]);

        $countBefore = Notification::where('user_id', $admin->id)->count();

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        // Tidak ada notifikasi baru dibuat
        $countAfter = Notification::where('user_id', $admin->id)->count();
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_semua_admin_mendapat_notifikasi(): void
    {
        $admin1  = User::factory()->admin()->create();
        $admin2  = User::factory()->admin()->create();
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->create([
            'status' => ReportStatus::Ditugaskan->value,
        ]);
        TaskAssignment::create([
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
        ]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$report->taskAssignments->first()->id}/status", [
                'status' => 'in_progress',
            ])
            ->assertOk();

        // Kedua admin harus mendapat notifikasi
        $this->assertEquals(1, Notification::where('user_id', $admin1->id)->count());
        $this->assertEquals(1, Notification::where('user_id', $admin2->id)->count());
    }

    public function test_notifikasi_admin_mengandung_type_task_assigned_saat_penugasan(): void
    {
        $admin   = User::factory()->admin()->create();
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->create([
            'status' => ReportStatus::Ditugaskan->value,
        ]);

        // Jalankan SyncReportTechnicians (via action langsung)
        $action = new \App\Actions\SyncReportTechnicians();
        $action($report, [$teknisi->id]);

        // Teknisi mendapat notifikasi task_assigned
        $notifTeknisi = Notification::where('user_id', $teknisi->id)->first();
        $this->assertNotNull($notifTeknisi);
        $this->assertEquals(NotificationType::TaskAssigned->value, $notifTeknisi->type);
        $this->assertEquals($report->id, $notifTeknisi->related_id);
    }

    /**
     * Helper: assertStringContains karena PHPUnit tidak punya bawaan.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
