<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Laporan Riwayat')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
        }
        @page { margin: 90px 32px 48px 32px; }

        /* ---- Kop / header dokumen (fixed di tiap halaman) ---- */
        .kop {
            position: fixed;
            top: -70px; left: 0; right: 0;
            border-bottom: 2px solid #2563EB;
            padding-bottom: 8px;
        }
        .kop .brand {
            font-size: 18px;
            font-weight: bold;
            color: #2563EB;
            letter-spacing: .3px;
        }
        .kop .subtitle {
            font-size: 9px;
            color: #475569;
            margin-top: 2px;
        }

        /* ---- Footer (fixed) ---- */
        .footer {
            position: fixed;
            bottom: -30px; left: 0; right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }
        .footer .pagenum:after { content: counter(page); }
        .footer .pagetotal:after { content: counter(pages); }

        /* ---- Meta filter (mengalir di awal konten) ---- */
        .meta {
            width: 100%;
            margin-bottom: 14px;
            font-size: 9px;
            color: #475569;
        }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta .label { color: #94a3b8; width: 70px; }
        .meta .sep { width: 10px; }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 10px 0;
        }

        /* ---- Badge status ---- */
        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-info    { background: #e0f2fe; color: #0369a1; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-muted   { background: #f1f5f9; color: #64748b; }

        .text-muted { color: #94a3b8; }
        .empty {
            text-align: center;
            color: #94a3b8;
            padding: 30px 0;
            font-style: italic;
        }

        @yield('styles')
    </style>
</head>
<body>
    {{-- Kop dokumen --}}
    <div class="kop">
        <div class="brand">{{ $meta['brand'] }}</div>
        <div class="subtitle">{{ $meta['subtitle'] }}</div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        {{ $meta['brand'] }} &nbsp;•&nbsp; Halaman <span class="pagenum"></span> dari <span class="pagetotal"></span>
    </div>

    {{-- Judul + meta filter (tiap template isi sendiri via @section('meta')) --}}
    <div class="doc-title">@yield('title', 'Laporan Riwayat Perbaikan')</div>
    @yield('meta')

    @yield('content')
</body>
</html>
