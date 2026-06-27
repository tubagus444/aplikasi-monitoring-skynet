<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Exports\CustomersExport;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomerExportController extends Controller
{
    /**
     * Ekspor daftar pelanggan ke PDF (ber-kop), mengikuti filter search + status
     * yang aktif di halaman. Filter dipusatkan di scope `Customer::filtered`.
     */
    public function pdf(Request $request): Response
    {
        \Carbon\Carbon::setLocale('id');

        [$search, $status] = $this->filters($request);

        $customers = Customer::filtered($search, $status)->get();

        $meta = [
            'brand'       => 'SkyNet RT/RW Net',
            'subtitle'    => 'Daftar Pelanggan — Kab. Bekasi',
            'statusLabel' => $status ? (CustomerStatus::tryFrom($status)?->label() ?? $status) : 'Semua status',
            'search'      => $search,
            'printedAt'   => now()->translatedFormat('d F Y, H:i'),
            'total'       => $customers->count(),
        ];

        return Pdf::loadView('pdf.pelanggan', ['customers' => $customers, 'meta' => $meta])
            ->setPaper('a4', 'landscape')
            ->stream('pelanggan.pdf');
    }

    /**
     * Ekspor daftar pelanggan ke Excel (.xlsx), filter sama dengan PDF & halaman.
     */
    public function excel(Request $request): BinaryFileResponse
    {
        [$search, $status] = $this->filters($request);

        return Excel::download(new CustomersExport($search, $status), 'pelanggan.xlsx');
    }

    /**
     * Baca & normalisasi filter dari query string (search + status), null bila kosong.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function filters(Request $request): array
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;

        // Tolak nilai status yang tidak dikenal (manipulasi query) → anggap tak difilter.
        if ($status !== null && ! in_array($status, CustomerStatus::values(), true)) {
            $status = null;
        }

        return [$search, $status];
    }
}
