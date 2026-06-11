@props(['status', 'pulse' => false])

@php
    /**
     * Pil status laporan: titik warna + label ringkas (dot+pill).
     * Kelas Tailwind ditulis literal di sini — komponen ada di resources/views
     * sehingga terbaca scanner. Label sengaja ringkas ("Memperbaiki") agar muat
     * di pil kompak. Sumber kebenaran nilai status = App\Enums\ReportStatus.
     *
     * Prop:
     *  - status : string|ReportStatus nilai status laporan
     *  - pulse  : bila true, titik berdenyut saat status "sedang_memperbaiki"
     *             (dipakai dashboard untuk indikator "hidup"; tabel laporan tidak)
     */
    $case = $status instanceof \App\Enums\ReportStatus
        ? $status
        : \App\Enums\ReportStatus::tryFrom($status);

    $style = match ($case) {
        \App\Enums\ReportStatus::Ditugaskan        => ['pill' => 'bg-warning/15 text-warning', 'dot' => 'bg-warning',              'label' => 'Ditugaskan'],
        \App\Enums\ReportStatus::SedangMemperbaiki => ['pill' => 'bg-info/15 text-info',       'dot' => 'bg-info',                 'label' => 'Memperbaiki'],
        \App\Enums\ReportStatus::Selesai           => ['pill' => 'bg-success/15 text-success', 'dot' => 'bg-success',              'label' => 'Selesai'],
        default                                    => ['pill' => 'bg-base-200 text-base-content/60', 'dot' => 'bg-base-content/40', 'label' => (string) $status],
    };

    $pillClass = $style['pill'];
    $label     = $style['label'];
    $dotClass  = $style['dot'] . ($pulse && $case === \App\Enums\ReportStatus::SedangMemperbaiki ? ' animate-pulse' : '');
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium $pillClass") }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
    {{ $label }}
</span>
