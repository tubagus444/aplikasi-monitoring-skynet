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
 * Halaman Statistik (Rencana #3): agregat laporan & kinerja teknisi, plus filter
 * kategori di scope riwayat. Menjaga query agregat tetap benar & DB-agnostic.
 */
class StatistikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_halaman_statistik_dapat_dirender(): void
    {
        $this->get('/statistik')
            ->assertOk()
            ->assertSee('Statistik')
            ->assertSee('Tren Komplain per Bulan')
            ->assertSee('Kinerja Teknisi')
            ->assertSee('Kerusakan Tersering');
    }

    public function test_kerusakan_tersering_dan_kinerja_teknisi_tampil(): void
    {
        $jenis   = DamageType::factory()->create(['name' => 'Kabel Rumah Putus']);
        $teknisi = User::factory()->teknisi()->create(['name' => 'Budi Teknisi']);
        $report  = DamageReport::factory()->selesai()->create(['damage_type_id' => $jenis->id]);

        TaskAssignment::create([
            'report_id'     => $report->id,
            'technician_id' => $teknisi->id,
            'assigned_at'   => now(),
        ]);

        Volt::test('pages.statistik')
            ->assertSee('Kabel Rumah Putus')
            ->assertSee('Budi Teknisi');
    }

    public function test_kinerja_teknisi_menghitung_ditangani_dan_selesai(): void
    {
        $teknisi  = User::factory()->teknisi()->create();
        $selesai  = DamageReport::factory()->selesai()->create();
        $berjalan = DamageReport::factory()->sedangDiperbaiki()->create();

        foreach ([$selesai, $berjalan] as $report) {
            TaskAssignment::create([
                'report_id'     => $report->id,
                'technician_id' => $teknisi->id,
                'assigned_at'   => now(),
            ]);
        }

        $kinerja = Volt::test('pages.statistik')->instance()->kinerjaTeknisi()->firstWhere('id', $teknisi->id);

        $this->assertSame(2, $kinerja->tugas_ditangani);
        $this->assertSame(1, $kinerja->tugas_selesai);
    }

    public function test_scope_riwayat_selesai_filter_kategori(): void
    {
        DamageReport::factory()->selesai()->create();                 // kategori pelanggan
        DamageReport::factory()->jaringan()->selesai()->create();     // kategori jaringan

        $this->assertCount(2, DamageReport::riwayatSelesai()->get());
        $this->assertCount(1, DamageReport::riwayatSelesai(null, null, 'jaringan')->get());
        $this->assertSame(
            'jaringan',
            DamageReport::riwayatSelesai(null, null, 'jaringan')->first()->category,
        );
    }

    public function test_format_durasi(): void
    {
        $inst = Volt::test('pages.statistik')->instance();

        $this->assertSame('—', $inst->formatDurasi(null));
        $this->assertSame('30 menit', $inst->formatDurasi(30));
        $this->assertSame('2 jam', $inst->formatDurasi(120));
        $this->assertSame('1 hari', $inst->formatDurasi(1440));
    }

    public function test_rata_rata_durasi_dihitung_dari_laporan_selesai(): void
    {
        $report = DamageReport::factory()->selesai()->create(['completed_at' => now()]);
        $report->forceFill(['created_at' => now()->subHours(2)])->save();

        $menit = Volt::test('pages.statistik')->instance()->rataDurasiMenit();

        $this->assertGreaterThanOrEqual(115, $menit);
        $this->assertLessThanOrEqual(125, $menit);
    }
}
