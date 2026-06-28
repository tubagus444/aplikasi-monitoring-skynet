@props(['category'])

@php
    /**
     * Pil kategori laporan: titik warna + label ringkas (dot+pill). Membedakan
     * laporan pelanggan dari jaringan/pemeliharaan di tabel Laporan & Riwayat
     * (kolom "Laporan" memakai headline `judul` yang bisa nama pelanggan ATAU
     * judul pekerjaan — pil ini yang menegaskan jenisnya).
     *
     * Kelas Tailwind ditulis literal di sini agar terbaca scanner. Warna selaras
     * kartu pemilih kategori di form laporan (pelanggan=primary, jaringan=info,
     * pemeliharaan=secondary). Sumber kebenaran nilai = App\Enums\ReportCategory.
     *
     * Prop:
     *  - category : string|ReportCategory nilai kategori laporan
     */
    $case = $category instanceof \App\Enums\ReportCategory
        ? $category
        : \App\Enums\ReportCategory::tryFrom($category);

    $style = match ($case) {
        \App\Enums\ReportCategory::Pelanggan    => ['pill' => 'bg-primary/15 text-primary',     'dot' => 'bg-primary',            'label' => 'Pelanggan'],
        \App\Enums\ReportCategory::Jaringan     => ['pill' => 'bg-info/15 text-info',           'dot' => 'bg-info',               'label' => 'Jaringan'],
        \App\Enums\ReportCategory::Pemeliharaan => ['pill' => 'bg-secondary/15 text-secondary', 'dot' => 'bg-secondary',          'label' => 'Pemeliharaan'],
        default                                 => ['pill' => 'bg-base-200 text-base-content/60', 'dot' => 'bg-base-content/40', 'label' => (string) $category],
    };
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {$style['pill']}") }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $style['dot'] }}"></span>
    {{ $style['label'] }}
</span>
