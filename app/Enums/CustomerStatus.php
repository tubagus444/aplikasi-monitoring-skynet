<?php

namespace App\Enums;

/**
 * Status langganan pelanggan — sumber kebenaran tunggal.
 *
 * Nilai enum = nilai yang tersimpan di kolom `customers.status`. Gunakan enum ini
 * alih-alih menulis string literal ('aktif'/'isolir'/'berhenti') agar typo terdeteksi
 * saat kompilasi, bukan jadi bug senyap. Sejajar dengan {@see ReportStatus} & {@see UserRole}.
 *
 * Catatan: kolom `status` sengaja TIDAK di-cast ke enum (sama pola dengan
 * `users.role` & `damage_reports.status`) — di query & logika pakai `CustomerStatus::X->value`.
 */
enum CustomerStatus: string
{
    case Aktif = 'aktif';
    case Isolir = 'isolir';
    case Berhenti = 'berhenti';

    /** Label Bahasa Indonesia untuk ditampilkan di UI. */
    public function label(): string
    {
        return match ($this) {
            self::Aktif    => 'Aktif',
            self::Isolir   => 'Isolir',
            self::Berhenti => 'Berhenti',
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
     * Daftar nilai mentah untuk aturan validasi `in:...`.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
