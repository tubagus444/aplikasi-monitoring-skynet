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

    /**
     * Peta aksi API Android → transisi status laporan. Android memakai kosakata
     * terpisah (`in_progress`/`done`); INI satu-satunya tempat penerjemahannya ke
     * transisi enum, sehingga TaskController cukup jadi orkestrator (tak lagi
     * menyimpan tabel transisi sebagai literal). Transisi searah:
     * Ditugaskan → SedangMemperbaiki → Selesai.
     *
     * @return array<string, array{from: self, to: self}>
     */
    private static function apiTransitions(): array
    {
        return [
            'in_progress' => ['from' => self::Ditugaskan,        'to' => self::SedangMemperbaiki],
            'done'        => ['from' => self::SedangMemperbaiki, 'to' => self::Selesai],
        ];
    }

    /**
     * Kosakata aksi yang valid dikirim Android — sumber untuk aturan validasi `in:...`.
     *
     * @return array<int, string>
     */
    public static function apiActions(): array
    {
        return array_keys(self::apiTransitions());
    }

    /**
     * Transisi (`from`/`to`) untuk satu aksi API Android, atau null bila aksinya
     * tak dikenal (validasi `in:` semestinya sudah menyaring lebih dulu).
     *
     * @return array{from: self, to: self}|null
     */
    public static function transitionForApiAction(string $action): ?array
    {
        return self::apiTransitions()[$action] ?? null;
    }
}
