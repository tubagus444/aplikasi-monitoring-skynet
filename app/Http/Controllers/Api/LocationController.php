<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\LocationLog;
use App\Models\TaskAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'report_id'   => 'required|integer|exists:damage_reports,id',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'recorded_at' => 'nullable|date',
        ]);

        $assigned = TaskAssignment::where('technician_id', $request->user()->id)
            ->where('report_id', $request->report_id)
            ->whereHas('report', fn($q) => $q->where('status', ReportStatus::SedangMemperbaiki->value))
            ->exists();

        if (! $assigned) {
            return response()->json([
                'message' => 'Tidak dapat mengirim lokasi — laporan tidak aktif atau bukan tugas Anda',
            ], 403);
        }

        // Waktu pengambilan GPS di device lebih akurat daripada waktu sampai
        // server (bisa tertunda saat sinyal teknisi lemah). Abaikan jam device
        // yang melenceng ke masa depan agar "last_update" tidak janggal.
        $recordedAt = $request->filled('recorded_at')
            ? Carbon::parse($request->recorded_at)
            : now();

        if ($recordedAt->isFuture()) {
            $recordedAt = now();
        }

        LocationLog::create([
            'technician_id' => $request->user()->id,
            'report_id'     => $request->report_id,
            'latitude'      => $request->latitude,
            'longitude'     => $request->longitude,
            'recorded_at'   => $recordedAt,
        ]);

        return response()->json(['message' => 'Lokasi dicatat']);
    }
}
