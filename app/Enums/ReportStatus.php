<?php

namespace App\Enums;

/**
 * Status laporan kerusakan — sumber kebenaran tunggal.
 *
 * Nilai enum = nilai yang tersimpan di kolom `damage_reports.status`
 * dan `work_logs.status`. Gunakan enum ini alih-alih menulis string literal
 * agar typo terdeteksi saat kompilasi, bukan jadi bug senyap.
 *
 * Catatan kontrak API Android: aplikasi Android mengirim `in_progress`/`done`
 * (kosakata terpisah) yang dipetakan ke {@see self::SedangMemperbaiki} /
 * {@see self::Selesai} di TaskController. `Ditugaskan` hanya dibuat dari web admin.
 */
enum ReportStatus: string
{
    case Ditugaskan = 'ditugaskan';
    case SedangMemperbaiki = 'sedang_memperbaiki';
    case Selesai = 'selesai';

    /** Label Bahasa Indonesia untuk ditampilkan di UI. */
    public function label(): string
    {
        return match ($this) {
            self::Ditugaskan       => 'Ditugaskan',
            self::SedangMemperbaiki => 'Sedang Memperbaiki',
            self::Selesai          => 'Selesai',
        };
    }

    /**
     * Pasangan value => label untuk filter/dropdown.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $status) => $carry + [$status->value => $status->label()],
            [],
        );
    }
}
