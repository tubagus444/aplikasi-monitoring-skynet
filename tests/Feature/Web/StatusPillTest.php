<?php

namespace Tests\Feature\Web;

use App\Enums\ReportStatus;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Unit komponen <x-status-pill>: memastikan label, warna, dan perilaku pulse
 * konsisten setelah blok match() di dashboard & laporan disatukan ke komponen.
 */
class StatusPillTest extends TestCase
{
    public function test_menampilkan_label_dan_warna_per_status(): void
    {
        $cases = [
            'ditugaskan'         => ['Ditugaskan',  'bg-warning/15', 'text-warning'],
            'sedang_memperbaiki' => ['Memperbaiki', 'bg-info/15',    'text-info'],
            'selesai'            => ['Selesai',     'bg-success/15', 'text-success'],
        ];

        foreach ($cases as $status => [$label, $pill, $text]) {
            $html = Blade::render('<x-status-pill :status="$s" />', ['s' => $status]);

            $this->assertStringContainsString($label, $html, "label {$status}");
            $this->assertStringContainsString($pill, $html, "kelas pil {$status}");
            $this->assertStringContainsString($text, $html, "kelas teks {$status}");
        }
    }

    public function test_pulse_hanya_aktif_saat_sedang_memperbaiki(): void
    {
        // prop pulse + status memperbaiki → titik berdenyut (indikator "hidup")
        $this->assertStringContainsString(
            'animate-pulse',
            Blade::render('<x-status-pill :status="$s" pulse />', ['s' => 'sedang_memperbaiki'])
        );

        // tanpa prop pulse → diam walau memperbaiki (perilaku tabel laporan)
        $this->assertStringNotContainsString(
            'animate-pulse',
            Blade::render('<x-status-pill :status="$s" />', ['s' => 'sedang_memperbaiki'])
        );

        // pulse aktif tapi status lain → tetap diam
        $this->assertStringNotContainsString(
            'animate-pulse',
            Blade::render('<x-status-pill :status="$s" pulse />', ['s' => 'selesai'])
        );
    }

    public function test_menerima_enum_maupun_string(): void
    {
        $html = Blade::render('<x-status-pill :status="$s" />', ['s' => ReportStatus::Selesai]);

        $this->assertStringContainsString('Selesai', $html);
        $this->assertStringContainsString('bg-success/15', $html);
    }
}
