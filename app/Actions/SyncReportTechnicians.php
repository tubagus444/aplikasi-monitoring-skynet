<?php

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\ReportCategory;
use App\Models\DamageReport;
use App\Models\Notification;
use App\Models\TaskAssignment;

/**
 * Sinkronkan penugasan teknisi sebuah laporan, lalu kirim notifikasi HANYA ke
 * teknisi yang baru ditambahkan (yang sudah ditugaskan tidak dinotifikasi ulang).
 *
 * Bersifat diff-based: hanya penugasan yang dilepas yang dihapus dan hanya yang
 * baru yang dibuat — penugasan lama dibiarkan utuh agar `assigned_at`-nya tidak
 * ter-reset saat laporan diedit.
 *
 * Dipakai bersama oleh alur buat & edit laporan agar logikanya satu sumber:
 * pada alur "buat", belum ada teknisi lama sehingga seluruh pilihan dianggap baru.
 * Notifikasi FCM terkirim otomatis via NotificationObserver saat Notification dibuat.
 */
class SyncReportTechnicians
{
    /**
     * @param  array<int|string>  $technicianIds
     */
    public function __invoke(DamageReport $report, array $technicianIds): void
    {
        $technicianIds = array_map('intval', $technicianIds);
        $existingIds   = $report->taskAssignments()->pluck('technician_id')->all();

        $toRemove = array_diff($existingIds, $technicianIds);
        $toAdd    = array_diff($technicianIds, $existingIds);

        if ($toRemove) {
            $report->taskAssignments()
                ->whereIn('technician_id', $toRemove)
                ->delete();
        }

        // Pesan notifikasi sadar kategori: laporan pelanggan menyebut nama pelanggan;
        // laporan jaringan/pemeliharaan (tanpa pelanggan) memakai judul laporan.
        $body = $report->category === ReportCategory::Pelanggan->value
            ? "Anda ditugaskan untuk menangani gangguan di {$report->address} atas nama pelanggan {$report->customer_name}."
            : "Anda ditugaskan untuk menangani \"{$report->judul}\" di {$report->address}.";

        foreach ($toAdd as $technicianId) {
            TaskAssignment::create([
                'report_id'     => $report->id,
                'technician_id' => $technicianId,
            ]);

            Notification::create([
                'user_id'    => $technicianId,
                'title'      => 'Tugas Baru Ditugaskan',
                'body'       => $body,
                'type'       => NotificationType::TaskAssigned->value,
                'related_id' => $report->id,
            ]);
        }
    }
}
