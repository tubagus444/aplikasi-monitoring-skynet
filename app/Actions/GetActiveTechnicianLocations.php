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
 * HANYA titik terakhir per (teknisi, laporan) yang ditarik ke memori, lewat
 * subquery berkorelasi `recorded_at = MAX(recorded_at)` tiap pasangan — BUKAN
 * seluruh jejak GPS. Jejak bisa membengkak ribuan baris selama sesi kerja,
 * sementara yang dipakai hanya titik paling baru; menariknya semua hanya untuk
 * dibuang itulah yang dihindari (query ini jalan tiap 10 detik). Tetap SATU
 * query (anti N+1, jumlah query konstan walau teknisi bertambah) dan ditopang
 * index (technician_id, report_id, recorded_at).
 */
class GetActiveTechnicianLocations
{
    /**
     * Ambang "GPS basi": titik terakhir lebih tua dari ini dianggap usang
     * (sinyal/HP teknisi mungkin mati) → ditandai agar admin tak menyangka
     * teknisi diam di titik itu. Polling peta tiap 10 detik, jadi 5 menit
     * memberi toleransi lapang sebelum dianggap terputus.
     */
    private const STALE_AFTER_MINUTES = 5;

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

        // Titik TERAKHIR per (teknisi, laporan) dalam satu query: subquery
        // berkorelasi memilih baris ber-recorded_at maksimum tiap pasangan,
        // jadi tabel sebesar apa pun hanya menyumbang ~1 baris per teknisi
        // (bukan seluruh jejak GPS). Portabel di MySQL & SQLite (test).
        $table = (new LocationLog)->getTable();

        $latestByPair = LocationLog::whereIn('technician_id', $assignments->pluck('technician_id'))
            ->whereIn('report_id', $assignments->pluck('report_id'))
            ->whereRaw("recorded_at = (
                select max(recorded_at) from {$table} as latest
                where latest.technician_id = {$table}.technician_id
                  and latest.report_id = {$table}.report_id
            )")
            ->get()
            ->groupBy(fn ($log) => $log->technician_id . '-' . $log->report_id);

        return $assignments->map(function ($assignment) use ($latestByPair) {
            $latest = $latestByPair->get($assignment->technician_id . '-' . $assignment->report_id)?->first();

            return [
                'id'          => $assignment->technician_id,
                'name'        => $assignment->technician->name,
                'customer'    => $assignment->report->judul,
                'address'     => $assignment->report->address,
                'damage_type' => $assignment->report->damageType?->name,
                'latitude'    => $latest?->latitude,
                'longitude'   => $latest?->longitude,
                'last_update' => $latest?->recorded_at?->diffForHumans(),
                // null = belum ada GPS sama sekali ("Menunggu GPS", bukan basi);
                // true = ada titik tapi sudah usang ("GPS terputus").
                'is_stale'    => $latest?->recorded_at
                    ? $latest->recorded_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))
                    : false,
            ];
        })->all();
    }
}
