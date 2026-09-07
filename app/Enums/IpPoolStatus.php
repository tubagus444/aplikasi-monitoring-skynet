<?php

namespace App\Enums;

/**
 * Status alokasi IP Address pada IP Pool.
 *
 * Nilai enum = nilai yang tersimpan di kolom `ip_pools.status`.
 * - Tersedia: IP bebas dan siap dialokasikan ke pelanggan baru.
 * - Terpakai: IP sedang aktif digunakan oleh pelanggan tertentu.
 * - Reserved: IP dicadangkan untuk infrastruktur internal (Gateway, AP, Router tiang).
 */
enum IpPoolStatus: string
{
    case Tersedia = 'tersedia';
    case Terpakai = 'terpakai';
    case Reserved = 'reserved';

    /** Label Bahasa Indonesia untuk ditampilkan di antarmuka. */
    public function label(): string
    {
        return match ($this) {
            self::Tersedia => 'Tersedia',
            self::Terpakai => 'Terpakai',
            self::Reserved => 'Reserved',
        };
    }

    /** Kelas styling CSS pill / badge untuk antarmuka. */
    public function style(): array
    {
        return match ($this) {
            self::Tersedia => ['pill' => 'bg-success/15 text-success', 'dot' => 'bg-success'],
            self::Terpakai => ['pill' => 'bg-info/15 text-info',       'dot' => 'bg-info'],
            self::Reserved => ['pill' => 'bg-warning/15 text-warning', 'dot' => 'bg-warning'],
        };
    }

    /**
     * Pasangan value => label untuk dropdown/filter.
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
