<?php

namespace App\Enums;

/**
 * Peran pengguna — sumber kebenaran tunggal.
 *
 * Nilai enum = nilai yang tersimpan di kolom `users.role`. Gunakan enum ini
 * alih-alih menulis string literal ('admin'/'teknisi') agar typo terdeteksi
 * saat kompilasi, bukan jadi bug senyap. Sejajar dengan {@see ReportStatus}.
 *
 * Catatan: kolom `role` sengaja TIDAK di-cast ke enum (sama seperti `status`
 * pada DamageReport) — di query & logika pakai `UserRole::X->value`.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Teknisi = 'teknisi';

    /** Label Bahasa Indonesia untuk ditampilkan di UI. */
    public function label(): string
    {
        return match ($this) {
            self::Admin   => 'Admin',
            self::Teknisi => 'Teknisi',
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
            fn (array $carry, self $role) => $carry + [$role->value => $role->label()],
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
