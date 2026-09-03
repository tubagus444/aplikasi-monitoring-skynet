<?php

namespace App\Enums;

/**
 * Jenis notifikasi — sumber kebenaran tunggal.
 *
 * Nilai enum = nilai yang tersimpan di kolom `notifications.type`.
 * Dipakai bersama `related_id` untuk menentukan tujuan deep-link
 * (Android: navigasi ke detail tugas; Web: navigasi ke detail laporan).
 */
enum NotificationType: string
{
    case TaskAssigned = 'task_assigned';
    case TaskInProgress = 'task_in_progress';
    case TaskCompleted = 'task_completed';

    /** Label Bahasa Indonesia untuk ditampilkan di UI. */
    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned   => 'Tugas Ditugaskan',
            self::TaskInProgress => 'Teknisi Mulai Mengerjakan',
            self::TaskCompleted  => 'Tugas Selesai',
        };
    }

    /** Ikon Heroicon untuk tiap tipe notifikasi (dipakai halaman web admin). */
    public function icon(): string
    {
        return match ($this) {
            self::TaskAssigned   => 'o-clipboard-document-check',
            self::TaskInProgress => 'o-wrench-screwdriver',
            self::TaskCompleted  => 'o-check-circle',
        };
    }

    /** Warna DaisyUI untuk tiap tipe notifikasi. */
    public function color(): string
    {
        return match ($this) {
            self::TaskAssigned   => 'warning',
            self::TaskInProgress => 'info',
            self::TaskCompleted  => 'success',
        };
    }
}
