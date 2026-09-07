<?php

namespace App\Exports;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor Excel daftar pelanggan. Memakai scope filter yang sama dengan halaman &
 * ekspor PDF (`Customer::filtered`) — sumber kebenaran tunggal, ikut filter aktif.
 * Foto rumah sengaja tidak ikut ekspor (data master saja).
 */
class CustomersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private ?string $search = null,
        private ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return Customer::query()->with('internetPackage')->filtered($this->search, $this->status);
    }

    public function headings(): array
    {
        return ['Kode Pelanggan', 'Nama', 'No. HP', 'Alamat', 'IP Address', 'Paket', 'Status', 'Tanggal Pasang'];
    }

    /** @param Customer $customer */
    public function map($customer): array
    {
        return [
            $customer->customer_code ?? '—',
            $customer->name,
            $customer->phone,
            $customer->address,
            $customer->ip_address,
            $customer->internetPackage?->name,
            CustomerStatus::tryFrom($customer->status)?->label() ?? $customer->status,
            $customer->installed_at?->format('d/m/Y'),
        ];
    }
}
