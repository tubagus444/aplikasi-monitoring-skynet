<?php

namespace Tests\Feature\Web;

use App\Actions\GetActiveTechnicianLocations;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\LocationLog;
use App\Models\ReportPhoto;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Action lokasi teknisi aktif (sebelumnya tertanam & terduplikasi di monitoring
 * & dashboard). Menjamin: titik terbaru yang dipakai, teknisi tanpa GPS tetap
 * muncul (untuk monitoring), laporan non-aktif diabaikan, dan bebas N+1.
 */
class GetActiveTechnicianLocationsTest extends TestCase
{
    use RefreshDatabase;

    private function buatTeknisiAktif(?DamageType $type = null): array
    {
        $type   ??= DamageType::factory()->create();
        $tech     = User::factory()->teknisi()->create();
        $report   = DamageReport::factory()->sedangDiperbaiki()->create(['damage_type_id' => $type->id]);
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $tech->id]);

        return [$tech, $report];
    }

    public function test_mengambil_titik_gps_terbaru_teknisi_aktif(): void
    {
        [$tech, $report] = $this->buatTeknisiAktif();

        LocationLog::create([
            'technician_id' => $tech->id, 'report_id' => $report->id,
            'latitude' => -6.10, 'longitude' => 107.10,
            'recorded_at' => now()->subMinutes(5),
        ]);
        LocationLog::create([
            'technician_id' => $tech->id, 'report_id' => $report->id,
            'latitude' => -6.20, 'longitude' => 107.20,
            'recorded_at' => now(), // terbaru
        ]);

        $result = (new GetActiveTechnicianLocations)();

        $this->assertCount(1, $result);
        $this->assertEquals($tech->id, $result[0]['id']);
        $this->assertEquals(-6.20, $result[0]['latitude']);
        $this->assertEquals(107.20, $result[0]['longitude']);
    }

    public function test_teknisi_tanpa_gps_tetap_muncul_dengan_lokasi_null(): void
    {
        [$tech] = $this->buatTeknisiAktif();

        $result = (new GetActiveTechnicianLocations)();

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['latitude']);
        $this->assertNull($result[0]['last_update']);
        $this->assertFalse($result[0]['is_stale']); // belum ada GPS = "menunggu", bukan basi
    }

    public function test_titik_gps_terbaru_tidak_dianggap_basi(): void
    {
        [$tech, $report] = $this->buatTeknisiAktif();

        LocationLog::create([
            'technician_id' => $tech->id, 'report_id' => $report->id,
            'latitude' => -6.20, 'longitude' => 107.20,
            'recorded_at' => now()->subMinute(), // masih segar (< 5 menit)
        ]);

        $result = (new GetActiveTechnicianLocations)();

        $this->assertFalse($result[0]['is_stale']);
    }

    public function test_titik_gps_usang_ditandai_basi(): void
    {
        [$tech, $report] = $this->buatTeknisiAktif();

        LocationLog::create([
            'technician_id' => $tech->id, 'report_id' => $report->id,
            'latitude' => -6.20, 'longitude' => 107.20,
            'recorded_at' => now()->subMinutes(10), // lewat ambang 5 menit
        ]);

        $result = (new GetActiveTechnicianLocations)();

        $this->assertTrue($result[0]['is_stale']);
        $this->assertEquals(-6.20, $result[0]['latitude']); // lokasi terakhir tetap tampil
    }

    public function test_menyertakan_foto_bukti_pekerjaan_terbaru_dulu(): void
    {
        [$tech, $report] = $this->buatTeknisiAktif();

        // created_at tidak fillable (model append-only) → forceCreate untuk set waktu
        ReportPhoto::forceCreate([
            'report_id' => $report->id, 'path' => 'report_photos/lama.jpg',
            'caption' => 'sebelum', 'created_at' => now()->subMinutes(10),
        ]);
        ReportPhoto::forceCreate([
            'report_id' => $report->id, 'path' => 'report_photos/baru.jpg',
            'caption' => 'sesudah', 'created_at' => now(),
        ]);

        $result = (new GetActiveTechnicianLocations)();

        $this->assertCount(2, $result[0]['photos']);
        // Terbaru dulu + path dipetakan ke URL storage publik
        $this->assertStringContainsString('storage/report_photos/baru.jpg', $result[0]['photos'][0]['url']);
        $this->assertEquals('sesudah', $result[0]['photos'][0]['caption']);
        $this->assertStringContainsString('storage/report_photos/lama.jpg', $result[0]['photos'][1]['url']);
    }

    public function test_teknisi_tanpa_foto_dapat_array_kosong(): void
    {
        $this->buatTeknisiAktif();

        $result = (new GetActiveTechnicianLocations)();

        $this->assertSame([], $result[0]['photos']);
    }

    public function test_mengabaikan_laporan_yang_tidak_sedang_diperbaiki(): void
    {
        $type   = DamageType::factory()->create();
        $tech   = User::factory()->teknisi()->create();
        $report = DamageReport::factory()->create(['status' => 'ditugaskan', 'damage_type_id' => $type->id]);
        TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $tech->id]);

        $this->assertSame([], (new GetActiveTechnicianLocations)());
    }

    public function test_jumlah_query_konstan_tidak_n_plus_1(): void
    {
        $type = DamageType::factory()->create();

        // 1 teknisi aktif
        $this->buatTeknisiAktif($type);
        DB::flushQueryLog();
        DB::enableQueryLog();
        (new GetActiveTechnicianLocations)();
        $querySatuTeknisi = count(DB::getQueryLog());

        // Tambah 2 teknisi aktif (total 3)
        $this->buatTeknisiAktif($type);
        $this->buatTeknisiAktif($type);
        DB::flushQueryLog();
        (new GetActiveTechnicianLocations)();
        $queryTigaTeknisi = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(
            $querySatuTeknisi,
            $queryTigaTeknisi,
            'Jumlah query harus konstan walau teknisi bertambah (indikasi bebas N+1).',
        );
    }

    public function test_memprioritaskan_penugasan_terbaru_jika_teknisi_memiliki_beberapa_laporan_aktif(): void
    {
        $type = DamageType::factory()->create();
        $tech = User::factory()->teknisi()->create();

        // Laporan lama tanpa GPS
        $reportLama = DamageReport::factory()->sedangDiperbaiki()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'Pelanggan Lama',
        ]);
        TaskAssignment::create(['report_id' => $reportLama->id, 'technician_id' => $tech->id]);

        // Laporan baru dengan GPS
        $reportBaru = DamageReport::factory()->sedangDiperbaiki()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'Pelanggan Baru',
        ]);
        TaskAssignment::create(['report_id' => $reportBaru->id, 'technician_id' => $tech->id]);
        LocationLog::create([
            'technician_id' => $tech->id,
            'report_id'     => $reportBaru->id,
            'latitude'      => -6.22,
            'longitude'     => 107.04,
            'recorded_at'   => now(),
        ]);

        $result = (new GetActiveTechnicianLocations)();

        $this->assertCount(1, $result);
        $this->assertEquals('Pelanggan Baru', $result[0]['customer']);
        $this->assertEquals(-6.22, $result[0]['latitude']);
        $this->assertEquals(107.04, $result[0]['longitude']);
        $this->assertEquals(2, $result[0]['tasks_count']);
        $this->assertCount(2, $result[0]['tasks']);
        $this->assertEquals('Pelanggan Baru', $result[0]['tasks'][0]['customer']);
        $this->assertEquals('Pelanggan Lama', $result[0]['tasks'][1]['customer']);
    }

    public function test_merangkum_seluruh_tugas_aktif_teknisi_dalam_array_tasks(): void
    {
        $type = DamageType::factory()->create(['name' => 'Kabel Putus']);
        $tech = User::factory()->teknisi()->create(['name' => 'Budi Teknisi']);

        $r1 = DamageReport::factory()->sedangDiperbaiki()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'Pelanggan 1',
            'address'        => 'Jl. Mawar No. 1',
        ]);
        TaskAssignment::create(['report_id' => $r1->id, 'technician_id' => $tech->id]);

        $r2 = DamageReport::factory()->sedangDiperbaiki()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'Pelanggan 2',
            'address'        => 'Jl. Melati No. 2',
        ]);
        TaskAssignment::create(['report_id' => $r2->id, 'technician_id' => $tech->id]);

        $r3 = DamageReport::factory()->sedangDiperbaiki()->create([
            'damage_type_id' => $type->id,
            'customer_name'  => 'Pelanggan 3',
            'address'        => 'Jl. Anggrek No. 3',
        ]);
        TaskAssignment::create(['report_id' => $r3->id, 'technician_id' => $tech->id]);

        $result = (new GetActiveTechnicianLocations)();

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result[0]['tasks_count']);
        $this->assertCount(3, $result[0]['tasks']);

        $customers = array_column($result[0]['tasks'], 'customer');
        $this->assertContains('Pelanggan 1', $customers);
        $this->assertContains('Pelanggan 2', $customers);
        $this->assertContains('Pelanggan 3', $customers);
    }
}
