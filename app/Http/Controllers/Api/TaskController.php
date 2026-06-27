<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Models\WorkLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = TaskAssignment::with(['report.damageType', 'report.customer'])
            ->where('technician_id', $request->user()->id)
            ->whereHas('report', fn($q) => $q->whereIn('status', [ReportStatus::Ditugaskan->value, ReportStatus::SedangMemperbaiki->value]))
            ->latest('assigned_at')
            ->get()
            ->map(fn($a) => $this->formatTask($a));

        return response()->json(['data' => $tasks]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assignment = TaskAssignment::with([
                'report.damageType',
                'report.workLogs.technician',
                'report.customer.photos',
            ])
            ->where('technician_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $this->formatTask($assignment, detailed: true)]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', ReportStatus::apiActions()),
            // Catatan pekerjaan teknisi (opsional) — narasi "apa yang dikerjakan",
            // tersimpan di work log transisi ini & tampil di timeline riwayat/API detail.
            'description' => 'nullable|string|max:1000',
        ]);

        $assignment = TaskAssignment::where('technician_id', $request->user()->id)
            ->findOrFail($id);

        // Tabel transisi (kosakata Android → status enum) dimiliki ReportStatus;
        // controller cukup mengorkestrasi transaksi/lock/work-log di bawah.
        $transition = ReportStatus::transitionForApiAction($request->status);

        // Status milik bersama (level laporan) → dua teknisi bisa menekan tombol
        // yang sama nyaris bersamaan. Kunci baris laporan di dalam transaksi agar
        // pengecekan status & penulisan work log tidak balapan (cegah work log
        // ganda). lockForUpdate tak berlaku di SQLite (test) tapi query tetap jalan.
        return DB::transaction(function () use ($request, $assignment, $transition) {
            $report = $assignment->report()->lockForUpdate()->first();

            // Bila teknisi lain di tim sudah memindahkan laporan ke status tujuan
            // lebih dulu, permintaan ini idempotent: anggap sukses tanpa transisi
            // ulang & tanpa work log ganda.
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

            // Catatan menempel pada work log transisi yang baru dibuat. Pada jalur
            // idempotent di atas (status sudah sesuai) tidak ada work log dibuat,
            // jadi catatan memang sengaja tidak tersimpan — tak ada transisi untuk
            // dilekati.
            WorkLog::create([
                'report_id'     => $report->id,
                'technician_id' => $request->user()->id,
                'status'        => $transition['to']->value,
                'description'   => $request->input('description'),
            ]);

            return response()->json([
                'message' => 'Status diperbarui',
                'status'  => $transition['to']->value,
            ]);
        });
    }

    private function formatTask(TaskAssignment $assignment, bool $detailed = false): array
    {
        $report = $assignment->report;
        // Null untuk laporan non-pelanggan (kategori jaringan/pemeliharaan).
        $customer = $report->customer;

        $data = [
            'id'           => $assignment->id,
            'report_id'    => $report->id,
            'status'       => $report->status,
            'category'     => $report->category,
            // Headline tampilan, tak peduli kategori: customer_name ?? title.
            'headline'     => $report->judul,
            'customer'     => $report->customer_name,
            'address'      => $report->address,
            'damage_type'  => $report->damageType->name,
            'notes'        => $report->notes,
            'assigned_at'  => $assignment->assigned_at?->toIso8601String(),
            // Kontak & info teknis pelanggan — null untuk laporan non-pelanggan.
            'phone'                => $customer?->phone,
            'ip_address'           => $customer?->ip_address,
            'subscription_package' => $customer?->subscription_package,
        ];

        if ($detailed) {
            // Foto rumah (wayfinding) — URL absolut; kosong untuk non-pelanggan.
            $data['house_photos'] = $customer
                ? $customer->photos->map(fn ($p) => asset('storage/' . $p->path))->values()
                : [];

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
