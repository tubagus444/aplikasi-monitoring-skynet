<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat & ekspor PDF harus tahan kategori: laporan non-pelanggan (jaringan/
 * pemeliharaan) memakai accessor `judul` (customer_name ?? title) — tidak boleh
 * tampil kosong — dan template PDF tidak boleh error untuk laporan tanpa pelanggan.
 */
class RiwayatCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_riwayat_menampilkan_judul_dan_kategori(): void
    {
        DamageReport::factory()->selesai()->create(['customer_name' => 'Pak Hendra']);
        DamageReport::factory()->jaringan()->selesai()->create(['title' => 'Kabel Utama Putus']);

        $this->get('/history')
            ->assertOk()
            ->assertSee('Pak Hendra')                          // judul = customer_name (pelanggan)
            ->assertSee('Kabel Utama Putus')                   // judul = title (non-pelanggan)
            ->assertSee('Gangguan Jaringan/Infrastruktur');    // label kategori non-pelanggan
    }

    public function test_ekspor_pdf_ringkasan_dengan_laporan_non_pelanggan(): void
    {
        DamageReport::factory()->selesai()->create();
        DamageReport::factory()->jaringan()->selesai()->create(['title' => 'Kabel Utama Putus']);

        $response = $this->get(route('history.export', ['mode' => 'ringkasan']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_ekspor_pdf_lengkap_dengan_laporan_non_pelanggan(): void
    {
        DamageReport::factory()->pemeliharaan()->selesai()->create(['title' => 'Perawatan POP Tambun']);

        $response = $this->get(route('history.export', ['mode' => 'lengkap']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }
}
