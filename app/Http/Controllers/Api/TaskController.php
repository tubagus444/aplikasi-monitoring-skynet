<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Models\WorkLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = TaskAssignment::with(['report.damageType'])
            ->where('technician_id', $request->user()->id)
            ->whereHas('report', fn($q) => $q->whereIn('status', [ReportStatus::Ditugaskan->value, ReportStatus::SedangMemperbaiki->value]))
            ->latest('assigned_at')
            ->get()
            ->map(fn($a) => $this->formatTask($a));

        return response()->json(['data' => $tasks]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assignment = TaskAssignment::with(['report.damageType', 'report.workLogs.technician'])
            ->where('technician_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $this->formatTask($assignment, detailed: true)]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:in_progress,done',
        ]);

        $assignment = TaskAssignment::with('report')
            ->where('technician_id', $request->user()->id)
            ->findOrFail($id);

        $report = $assignment->report;

        $transition = [
            'in_progress' => ['from' => ReportStatus::Ditugaskan,        'to' => ReportStatus::SedangMemperbaiki],
            'done'        => ['from' => ReportStatus::SedangMemperbaiki, 'to' => ReportStatus::Selesai],
        ][$request->status];

        // Status milik bersama (level laporan). Bila teknisi lain di tim sudah
        // memindahkan laporan ke status tujuan lebih dulu, permintaan ini
        // bersifat idempotent: anggap sukses tanpa transisi & tanpa work log ganda.
        if ($report->status === $transition['to']->value) {
            return response()->json([
                'message' => 'Status sudah sesuai',
                'status'  => $report->status,
            ]);
        }

        if ($report->status !== $transition['from']->value) {
            return response()->json([
                'message' => 'Perubahan status tidak valid dari status saat ini',
            ], 422);
        }

        $updates = ['status' => $transition['to']->value];

        // Catat waktu selesai sebenarnya (sumber kebenaran, tahan terhadap edit
        // laporan berikutnya yang ikut mengubah updated_at).
        if ($transition['to'] === ReportStatus::Selesai) {
            $updates['completed_at'] = now();
        }

        $report->update($updates);

        WorkLog::create([
            'report_id'     => $report->id,
            'technician_id' => $request->user()->id,
            'status'        => $transition['to']->value,
        ]);

        return response()->json([
            'message' => 'Status diperbarui',
            'status'  => $transition['to']->value,
        ]);
    }

    private function formatTask(TaskAssignment $assignment, bool $detailed = false): array
    {
        $report = $assignment->report;

        $data = [
            'id'           => $assignment->id,
            'report_id'    => $report->id,
            'status'       => $report->status,
            'customer'     => $report->customer_name,
            'address'      => $report->address,
            'damage_type'  => $report->damageType->name,
            'notes'        => $report->notes,
            'assigned_at'  => $assignment->assigned_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['work_logs'] = $report->workLogs
                ->sortBy('logged_at')
                ->values()
                ->map(fn($log) => [
                    'status'     => $log->status,
                    'technician' => $log->technician?->name,
                    'description'=> $log->description,
                    'logged_at'  => $log->logged_at->toIso8601String(),
                ]);
        }

        return $data;
    }
}
