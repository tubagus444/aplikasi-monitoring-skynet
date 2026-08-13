@extends('pdf.layout')

@section('title', 'Daftar Pelanggan')

@section('styles')
    table.data { width: 100%; border-collapse: collapse; }
    table.data thead th {
        background: #2563EB;
        color: #fff;
        font-size: 9px;
        text-align: left;
        padding: 6px 7px;
        font-weight: bold;
    }
    table.data tbody td {
        padding: 5px 7px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 9px;
        vertical-align: top;
    }
    table.data tbody tr:nth-child(even) td { background: #f8fafc; }
    table.data .col-no { width: 28px; text-align: center; color: #94a3b8; }
    table.data .col-ip { width: 95px; }
    table.data .col-pkt { width: 70px; }
    table.data .col-sts { width: 70px; }
    table.data .col-tgl { width: 80px; }
@endsection

@section('meta')
    <table class="meta">
        <tr>
            <td class="label">Status</td><td class="sep">:</td><td>{{ $meta['statusLabel'] }}</td>
            <td class="label">Dicetak</td><td class="sep">:</td><td>{{ $meta['printedAt'] }}</td>
        </tr>
        <tr>
            <td class="label">Pencarian</td><td class="sep">:</td><td>{{ $meta['search'] ?: '—' }}</td>
            <td class="label">Total</td><td class="sep">:</td><td>{{ $meta['total'] }} pelanggan</td>
        </tr>
    </table>
@endsection

@section('content')
    @if($customers->isEmpty())
        <div class="empty">Tidak ada pelanggan untuk filter ini.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Nama</th>
                    <th>No. HP</th>
                    <th>Alamat</th>
                    <th class="col-ip">IP Address</th>
                    <th class="col-pkt">Paket</th>
                    <th class="col-sts">Status</th>
                    <th class="col-tgl">Tgl Pasang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $i => $customer)
                    @php
                        $badge = match($customer->status) {
                            \App\Enums\CustomerStatus::Aktif->value    => 'badge-success',
                            \App\Enums\CustomerStatus::Isolir->value   => 'badge-warning',
                            \App\Enums\CustomerStatus::Berhenti->value => 'badge-muted',
                            default                                    => 'badge-muted',
                        };
                        $statusLabel = \App\Enums\CustomerStatus::tryFrom($customer->status)?->label() ?? $customer->status;
                    @endphp
                    <tr>
                        <td class="col-no">{{ $i + 1 }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->phone ?: '—' }}</td>
                        <td>{{ $customer->address }}</td>
                        <td>{{ $customer->ip_address ?: '—' }}</td>
                        <td>{{ $customer->internetPackage?->name ?: '—' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $statusLabel }}</span></td>
                        <td>{{ $customer->installed_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
