<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    /**
     * Ekspor riwayat laporan selesai ke PDF (mode ringkasan / lengkap),
     * mengikuti filter pencarian & periode yang sedang aktif di halaman.
     */
    public function riwayat(Request $request): Response
    {
        \Carbon\Carbon::setLocale('id');

        $search   = $request->string('search')->trim()->value() ?: null;
        $period   = $request->string('period')->trim()->value() ?: null;
        $category = $request->string('category')->trim()->value() ?: null;
        $mode     = $request->string('mode')->value() === 'lengkap' ? 'lengkap' : 'ringkasan';

        $reports = DamageReport::with([
                'damageType',
                'taskAssignments.technician',
                'workLogs.technician',
            ])
            ->riwayatSelesai($search, $period, $category)
            ->get();

        $meta = [
            'brand'        => 'SkyNet RT/RW Net',
            'subtitle'     => 'Laporan Riwayat Perbaikan Jaringan — Kab. Bekasi',
            'periodeLabel' => $this->periodeLabel($period),
            'kategoriLabel' => $category ? \App\Enums\ReportCategory::from($category)->label() : 'Semua Kategori',
            'search'       => $search,
            'printedAt'    => now()->translatedFormat('d F Y, H:i'),
            'total'        => $reports->count(),
        ];

        $view = $mode === 'lengkap' ? 'pdf.riwayat-lengkap' : 'pdf.riwayat-ringkasan';

        $pdf = Pdf::loadView($view, [
            'reports' => $reports,
            'meta'    => $meta,
        ])->setPaper('a4', $mode === 'lengkap' ? 'portrait' : 'landscape');

        return $pdf->stream("riwayat-{$mode}.pdf");
    }

    private function periodeLabel(?string $period): string
    {
        return match ($period) {
            'minggu' => 'Minggu Ini ('
                . now()->startOfWeek()->translatedFormat('d M')
                . ' – '
                . now()->endOfWeek()->translatedFormat('d M Y') . ')',
            'bulan'  => 'Bulan Ini (' . now()->translatedFormat('F Y') . ')',
            default  => 'Semua Periode',
        };
    }
}
