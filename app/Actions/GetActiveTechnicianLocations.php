<?php

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Models\LocationLog;
use App\Models\TaskAssignment;

/**
 * Kumpulkan lokasi GPS terakhir tiap teknisi yang sedang menangani laporan
 * berstatus "sedang_memperbaiki" — satu baris per teknisi (laporan aktif
 * pertamanya). Dipakai bersama oleh halaman Monitoring (poll 10 detik) dan
 * peta dashboard agar logikanya satu sumber.
 *
 * Titik terakhir per (teknisi, laporan) diambil dalam SATU query lalu
 * dikelompokkan di memori — menghindari N+1 (sebelumnya 1 query LocationLog
 * per teknisi pada tiap poll).
 */
class GetActiveTechnicianLocations
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        $assignments = TaskAssignment::with(['technician', 'report.damageType'])
            ->whereHas('report', fn ($q) => $q->where('status', ReportStatus::SedangMemperbaiki->value))
            ->get()
            ->unique('technician_id') // satu laporan aktif per teknisi (yang pertama)
            ->values();

        if ($assignments->isEmpty()) {
            return [];
        }

        // Titik terakhir per (teknisi, laporan) — satu query untuk semua teknisi.
        $latestByPair = LocationLog::whereIn('technician_id', $assignments->pluck('technician_id'))
            ->whereIn('report_id', $assignments->pluck('report_id'))
            ->orderByDesc('recorded_at')
            ->get()
            ->groupBy(fn ($log) => $log->technician_id . '-' . $log->report_id);

        return $assignments->map(function ($assignment) use ($latestByPair) {
            $latest = $latestByPair->get($assignment->technician_id . '-' . $assignment->report_id)?->first();

            return [
                'id'          => $assignment->technician_id,
                'name'        => $assignment->technician->name,
                'customer'    => $assignment->report->customer_name,
                'address'     => $assignment->report->address,
                'damage_type' => $assignment->report->damageType->name,
                'latitude'    => $latest?->latitude,
                'longitude'   => $latest?->longitude,
                'last_update' => $latest?->recorded_at?->diffForHumans(),
            ];
        })->all();
    }
}
