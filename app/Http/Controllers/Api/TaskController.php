<?php

namespace App\Http\Controllers\Api;

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
            ->whereHas('report', fn($q) => $q->whereIn('status', ['ditugaskan', 'sedang_memperbaiki']))
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
            'in_progress' => ['from' => 'ditugaskan',         'to' => 'sedang_memperbaiki'],
            'done'        => ['from' => 'sedang_memperbaiki',  'to' => 'selesai'],
        ][$request->status];

        if ($report->status !== $transition['from']) {
            return response()->json([
                'message' => 'Perubahan status tidak valid dari status saat ini',
            ], 422);
        }

        $report->update(['status' => $transition['to']]);

        WorkLog::create([
            'report_id'     => $report->id,
            'technician_id' => $request->user()->id,
            'status'        => $transition['to'],
        ]);

        return response()->json([
            'message' => 'Status diperbarui',
            'status'  => $transition['to'],
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
