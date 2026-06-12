<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Regresi #5: waktu selesai laporan harus memakai kolom `completed_at`, BUKAN
 * `updated_at`. Mengedit laporan yang sudah selesai mengubah `updated_at` ke
 * "sekarang" — dulu itu menggeser statistik & filter. `completed_at` kebal.
 *
 * Catatan: factory create() otomatis mengeset updated_at = sekarang, sehingga
 * laporan yang `completed_at`-nya di masa lalu sudah mensimulasikan "baris
 * ter-update belakangan" tanpa perlu memaksa updated_at manual.
 */
class CompletedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_periode_riwayat_memakai_completed_at(): void
    {
        // Selesai bulan lalu, tapi baris ter-update bulan ini (updated_at = sekarang)
        $bulanLalu = DamageReport::factory()->selesai()->create([
            'completed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
        ]);
        // Kontrol: benar-benar selesai bulan ini
        $bulanIni = DamageReport::factory()->selesai()->create([
            'completed_at' => now(),
        ]);

        $hasil = DamageReport::riwayatSelesai(null, 'bulan')->get();

        // Filter "bulan ini" harus mengikuti completed_at, bukan updated_at
        $this->assertTrue($hasil->contains('id', $bulanIni->id));
        $this->assertFalse($hasil->contains('id', $bulanLalu->id));
    }

    public function test_dashboard_selesai_hari_ini_memakai_completed_at(): void
    {
        DamageReport::factory()->selesai()->create(['completed_at' => now()]);            // hari ini
        DamageReport::factory()->selesai()->create(['completed_at' => now()->subWeek()]); // minggu lalu, baris baru dibuat hari ini

        $this->actingAs(User::factory()->admin()->create());

        $selesaiHariIni = Volt::test('pages.dashboard')->instance()->selesaiHariIni();

        $this->assertSame(1, $selesaiHariIni); // hanya yang completed_at-nya hari ini
    }

    public function test_durasi_penanganan_dan_waktu_selesai_terpusat_di_model(): void
    {
        $jam = DamageReport::factory()->selesai()->create([
            'created_at'   => now()->subHours(3),
            'completed_at' => now(),
        ]);
        $this->assertSame('3 jam', $jam->durasiPenanganan());

        $menit = DamageReport::factory()->selesai()->create([
            'created_at'   => now()->subMinutes(45),
            'completed_at' => now(),
        ]);
        $this->assertSame('45 menit', $menit->durasiPenanganan());
        $this->assertSame('45 mnt', $menit->durasiPenanganan(singkat: true)); // varian kolom sempit

        // waktu_selesai jatuh ke updated_at saat completed_at belum terisi
        $belum = DamageReport::factory()->create(['completed_at' => null]);
        $this->assertEquals($belum->updated_at->timestamp, $belum->waktu_selesai->timestamp);
    }

    public function test_modal_detail_riwayat_merender_durasi_dan_status_pill(): void
    {
        $report = DamageReport::factory()->selesai()->create([
            'created_at'   => now()->subHours(2),
            'completed_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('pages.riwayat')
            ->call('openDetail', $report->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('2 jam')    // durasiPenanganan() terpusat, di jalur modal
            ->assertSee('Selesai'); // <x-status-pill> ter-render tanpa error
    }
}
