<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationLog;
use App\Models\TaskAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'report_id' => 'required|integer|exists:damage_reports,id',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $assigned = TaskAssignment::where('technician_id', $request->user()->id)
            ->where('report_id', $request->report_id)
            ->whereHas('report', fn($q) => $q->where('status', 'sedang_memperbaiki'))
            ->exists();

        if (! $assigned) {
            return response()->json([
                'message' => 'Tidak dapat mengirim lokasi — laporan tidak aktif atau bukan tugas Anda',
            ], 403);
        }

        LocationLog::create([
            'technician_id' => $request->user()->id,
            'report_id'     => $request->report_id,
            'latitude'      => $request->latitude,
            'longitude'     => $request->longitude,
        ]);

        return response()->json(['message' => 'Lokasi dicatat']);
    }
}
