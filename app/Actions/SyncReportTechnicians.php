<?php

namespace App\Actions;

use App\Models\DamageReport;
use App\Models\Notification;
use App\Models\TaskAssignment;

/**
 * Sinkronkan penugasan teknisi sebuah laporan, lalu kirim notifikasi HANYA ke
 * teknisi yang baru ditambahkan (yang sudah ditugaskan tidak dinotifikasi ulang).
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
        $existingIds = $report->taskAssignments()->pluck('technician_id')->all();

        $report->taskAssignments()->delete();

        foreach ($technicianIds as $technicianId) {
            TaskAssignment::create([
                'report_id'     => $report->id,
                'technician_id' => $technicianId,
            ]);
        }

        foreach (array_diff($technicianIds, $existingIds) as $technicianId) {
            Notification::create([
                'user_id' => $technicianId,
                'title'   => 'Tugas Baru Ditugaskan',
                'body'    => "Anda ditugaskan untuk menangani laporan gangguan di {$report->address} atas nama pelanggan {$report->customer_name}.",
            ]);
        }
    }
}
