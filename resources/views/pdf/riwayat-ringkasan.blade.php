@extends('pdf.layout')

@section('title', 'Laporan Riwayat Perbaikan — Ringkasan')

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
    table.data .col-tgl { width: 95px; }
    table.data .col-dur { width: 60px; }
@endsection

@section('content')
    @if($reports->isEmpty())
        <div class="empty">Belum ada laporan selesai untuk filter ini.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Pelanggan</th>
                    <th>Alamat</th>
                    <th>Jenis Gangguan</th>
                    <th>Teknisi</th>
                    <th class="col-tgl">Selesai</th>
                    <th class="col-dur">Durasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reports as $i => $report)
                    @php
                        $teknisi = $report->taskAssignments->pluck('technician.name')->filter()->join(', ');
                    @endphp
                    <tr>
                        <td class="col-no">{{ $i + 1 }}</td>
                        <td>{{ $report->customer_name }}</td>
                        <td>{{ $report->address }}</td>
                        <td>{{ $report->damageType?->name ?? '—' }}</td>
                        <td>{{ $teknisi ?: '—' }}</td>
                        <td>{{ $report->waktu_selesai->format('d/m/Y H:i') }}</td>
                        <td>{{ $report->durasiPenanganan(singkat: true) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
