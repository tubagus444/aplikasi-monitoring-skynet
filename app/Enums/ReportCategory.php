<?php

namespace App\Enums;

/**
 * Kategori laporan kerusakan — sumber kebenaran tunggal.
 *
 * Nilai enum = nilai yang tersimpan di kolom `damage_reports.category`. Gunakan enum ini
 * alih-alih menulis string literal agar typo terdeteksi saat kompilasi, bukan jadi bug
 * senyap. Sejajar dengan {@see ReportStatus} & {@see UserRole}.
 *
 * Tidak semua pekerjaan milik satu pelanggan: hanya kategori {@see self::Pelanggan} yang
 * mewajibkan `customer_id`. Untuk {@see self::Jaringan}/{@see self::Pemeliharaan},
 * `customer_id` NULL dan laporan memakai `title` + `address` (lokasi/area terdampak).
 *
 * Catatan: kolom `category` sengaja TIDAK di-cast ke enum (sama pola dengan
 * `damage_reports.status`) — di query & logika pakai `ReportCategory::X->value`.
 */
enum ReportCategory: string
{
    case Pelanggan = 'pelanggan';
    case Jaringan = 'jaringan';
    case Pemeliharaan = 'pemeliharaan';

    /** Label Bahasa Indonesia untuk ditampilkan di UI. */
    public function label(): string
    {
        return match ($this) {
            self::Pelanggan    => 'Gangguan Pelanggan',
            self::Jaringan     => 'Gangguan Jaringan/Infrastruktur',
            self::Pemeliharaan => 'Pemeliharaan',
        };
    }

    /**
     * Apakah kategori ini mewajibkan pelanggan terdaftar (`customer_id`)?
     * Sumber kebenaran untuk validasi bersyarat di form laporan & dampak API Android.
     */
    public function butuhPelanggan(): bool
    {
        return $this === self::Pelanggan;
    }

    /**
     * Apakah kategori ini mewajibkan jenis gangguan (`damage_type_id`)?
     *
     * Hanya gangguan pelanggan riil yang wajib memilih jenis. Untuk `jaringan`/
     * `pemeliharaan` jenis bersifat opsional — pemeliharaan kerap bukan "kerusakan"
     * (pekerjaan preventif/terjadwal). Sejajar dengan {@see self::butuhPelanggan()}.
     */
    public function butuhJenisGangguan(): bool
    {
        return $this === self::Pelanggan;
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
            fn (array $carry, self $category) => $carry + [$category->value => $category->label()],
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
