@extends('pdf.layout')

@section('title', 'Laporan Riwayat Perbaikan — Lengkap')

@section('styles')
    .report {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px 14px;
        margin-bottom: 14px;
        page-break-inside: avoid;
    }
    .report .head {
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    .report .rid { font-size: 8px; color: #94a3b8; }
    .report .cust { font-size: 13px; font-weight: bold; color: #0f172a; }
    .report .dtype { font-size: 9px; color: #475569; }

    table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.info td { padding: 2px 0; vertical-align: top; font-size: 9px; }
    table.info .label { color: #94a3b8; width: 90px; }
    table.info .sep { width: 8px; }

    .logs-title {
        font-size: 8px;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .5px;
        margin: 4px 0 6px 0;
    }
    table.logs { width: 100%; border-collapse: collapse; }
    table.logs th {
        background: #f1f5f9;
        color: #475569;
        text-align: left;
        font-size: 8px;
        padding: 4px 6px;
    }
    table.logs td {
        padding: 4px 6px;
        border-bottom: 1px solid #eef2f6;
        font-size: 8.5px;
        vertical-align: top;
    }
    table.logs .col-wkt { width: 78px; }
    table.logs .col-sts { width: 110px; }
    table.logs .col-tek { width: 110px; }
@endsection

@section('meta')
    <table class="meta">
        <tr>
            <td class="label">Periode</td><td class="sep">:</td><td>{{ $meta['periodeLabel'] }}</td>
            <td class="label">Dicetak</td><td class="sep">:</td><td>{{ $meta['printedAt'] }}</td>
        </tr>
        <tr>
            <td class="label">Pencarian</td><td class="sep">:</td><td>{{ $meta['search'] ?: '—' }}</td>
            <td class="label">Total</td><td class="sep">:</td><td>{{ $meta['total'] }} laporan selesai</td>
        </tr>
    </table>
@endsection

@section('content')
    @if($reports->isEmpty())
        <div class="empty">Belum ada laporan selesai untuk filter ini.</div>
    @else
        @foreach($reports as $i => $report)
            @php
                $teknisi = $report->taskAssignments->pluck('technician.name')->filter()->join(', ');
            @endphp
            <div class="report">
                <div class="head">
                    <div class="rid">Laporan #{{ $report->id }} &nbsp;•&nbsp; No. {{ $i + 1 }}</div>
                    <span class="cust">{{ $report->judul }}</span>
                    <span class="badge badge-success">Selesai</span>
                    <div class="dtype">{{ $report->damageType?->name ?? '—' }}</div>
                </div>

                <table class="info">
                    <tr>
                        <td class="label">Kategori</td><td class="sep">:</td><td>{{ \App\Enums\ReportCategory::from($report->category)->label() }}</td>
                    </tr>
                    <tr>
                        <td class="label">Alamat</td><td class="sep">:</td><td>{{ $report->address }}</td>
                    </tr>
                    <tr>
                        <td class="label">Teknisi</td><td class="sep">:</td><td>{{ $teknisi ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Waktu Selesai</td><td class="sep">:</td><td>{{ $report->waktu_selesai->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Durasi</td><td class="sep">:</td><td>{{ $report->durasiPenanganan() }}</td>
                    </tr>
                    @if($report->notes)
                        <tr>
                            <td class="label">Keterangan</td><td class="sep">:</td><td>{{ $report->notes }}</td>
                        </tr>
                    @endif
                </table>

                <div class="logs-title">Log Aktivitas</div>
                @if($report->workLogs->isNotEmpty())
                    <table class="logs">
                        <thead>
                            <tr>
                                <th class="col-wkt">Waktu</th>
                                <th class="col-sts">Status</th>
                                <th class="col-tek">Teknisi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report->workLogs->sortBy('logged_at') as $log)
                                @php
                                    $badge = match($log->status) {
                                        'ditugaskan'         => 'badge-warning',
                                        'sedang_memperbaiki' => 'badge-info',
                                        'selesai'            => 'badge-success',
                                        default              => 'badge-muted',
                                    };
                                    $statusLabel = match($log->status) {
                                        'ditugaskan'         => 'Ditugaskan',
                                        'sedang_memperbaiki' => 'Mulai Memperbaiki',
                                        'selesai'            => 'Selesai',
                                        default              => $log->status,
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $log->logged_at->format('d/m H:i') }}</td>
                                    <td><span class="badge {{ $badge }}">{{ $statusLabel }}</span></td>
                                    <td>{{ $log->technician?->name ?? '—' }}</td>
                                    <td>{{ $log->description ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-muted" style="font-size:8.5px;">Belum ada log aktivitas.</div>
                @endif
            </div>
        @endforeach
    @endif
@endsection
