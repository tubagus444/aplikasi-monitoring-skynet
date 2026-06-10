<?php

namespace Tests\Feature\Api;

use App\Models\DamageReport;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teknisi_dapat_kirim_koordinat_gps(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->sedangDiperbaiki()->create();
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson('/api/location', [
                'report_id' => $report->id,
                'latitude'  => -6.2088,
                'longitude' => 106.8456,
            ])
            ->assertOk();
    }

    public function test_koordinat_gps_tersimpan_di_database(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->sedangDiperbaiki()->create();
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson('/api/location', [
                'report_id' => $report->id,
                'latitude'  => -6.2088,
                'longitude' => 106.8456,
            ]);

        $this->assertDatabaseHas('location_logs', [
            'technician_id' => $teknisi->id,
            'report_id'     => $report->id,
        ]);
    }

    public function test_recorded_at_dari_device_dipakai(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->sedangDiperbaiki()->create();
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $waktu = now()->subMinutes(3)->startOfSecond();

        $this->actingAs($teknisi, 'sanctum')
            ->postJson('/api/location', [
                'report_id'   => $report->id,
                'latitude'    => -6.2088,
                'longitude'   => 106.8456,
                'recorded_at' => $waktu->toIso8601String(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('location_logs', [
            'technician_id' => $teknisi->id,
            'report_id'     => $report->id,
            'recorded_at'   => $waktu->toDateTimeString(),
        ]);
    }

    public function test_recorded_at_di_masa_depan_diabaikan(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $report  = DamageReport::factory()->sedangDiperbaiki()->create();
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson('/api/location', [
                'report_id'   => $report->id,
                'latitude'    => -6.2088,
                'longitude'   => 106.8456,
                'recorded_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertOk();

        // Jam device melenceng → server pakai now(), bukan timestamp masa depan.
        $tersimpan = \App\Models\LocationLog::latest('id')->first();
        $this->assertFalse($tersimpan->recorded_at->isFuture());
    }

    public function test_kirim_gps_tanpa_autentikasi_ditolak(): void
    {
        $this->postJson('/api/location', [
            'latitude'  => -6.2088,
            'longitude' => 106.8456,
        ])->assertUnauthorized();
    }

    public function test_kirim_gps_tanpa_koordinat_ditolak(): void
    {
        $teknisi = User::factory()->teknisi()->create();

        $this->actingAs($teknisi, 'sanctum')
            ->postJson('/api/location', [])
            ->assertUnprocessable();
    }
}
