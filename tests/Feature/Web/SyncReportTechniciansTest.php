<?php

namespace Tests\Feature\Web;

use App\Actions\SyncReportTechnicians;
use App\Models\DamageReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perilaku inti penugasan teknisi (sebelumnya tertanam di save() laporan & tak ter-test):
 * sinkronisasi penugasan + notifikasi HANYA untuk teknisi yang baru ditambahkan.
 */
class SyncReportTechniciansTest extends TestCase
{
    use RefreshDatabase;

    public function test_menugaskan_teknisi_dan_mengirim_notifikasi(): void
    {
        $report = DamageReport::factory()->create();
        $t1 = User::factory()->teknisi()->create();
        $t2 = User::factory()->teknisi()->create();

        (new SyncReportTechnicians)($report, [$t1->id, $t2->id]);

        $this->assertEqualsCanonicalizing(
            [$t1->id, $t2->id],
            $report->taskAssignments()->pluck('technician_id')->all()
        );
        $this->assertEquals(2, Notification::count());
        $this->assertDatabaseHas('notifications', ['user_id' => $t1->id, 'title' => 'Tugas Baru Ditugaskan']);
        $this->assertDatabaseHas('notifications', ['user_id' => $t2->id]);
    }

    public function test_hanya_menotifikasi_teknisi_yang_baru_ditambahkan(): void
    {
        $report = DamageReport::factory()->create();
        $lama = User::factory()->teknisi()->create();
        $baru = User::factory()->teknisi()->create();

        // Penugasan awal: hanya teknisi lama
        (new SyncReportTechnicians)($report, [$lama->id]);
        Notification::query()->delete(); // bersihkan notifikasi penugasan awal

        // Sync ulang: tambah teknisi baru, pertahankan yang lama
        (new SyncReportTechnicians)($report, [$lama->id, $baru->id]);

        $this->assertEqualsCanonicalizing(
            [$lama->id, $baru->id],
            $report->taskAssignments()->pluck('technician_id')->all()
        );
        // Hanya teknisi baru yang dinotifikasi; yang lama tidak diganggu
        $this->assertEquals(1, Notification::count());
        $this->assertDatabaseHas('notifications', ['user_id' => $baru->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $lama->id]);
    }

    public function test_melepas_penugasan_yang_tidak_dipilih_lagi(): void
    {
        $report = DamageReport::factory()->create();
        $a = User::factory()->teknisi()->create();
        $b = User::factory()->teknisi()->create();

        (new SyncReportTechnicians)($report, [$a->id, $b->id]);
        (new SyncReportTechnicians)($report, [$a->id]); // lepas teknisi b

        $this->assertEqualsCanonicalizing(
            [$a->id],
            $report->taskAssignments()->pluck('technician_id')->all()
        );
    }
}
