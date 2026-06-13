@props(['options', 'field', 'selected' => ''])

{{--
    Grup chip filter (pil): satu tombol per opsi value => label. Dipakai bersama
    halaman Laporan (filterStatus) & Pengguna (filterRole) agar markup tidak
    diduplikasi. Kelas warna ditulis literal di sini (terbaca scanner Tailwind).

    wire:click di-resolve ke komponen Livewire induk — Blade component dikompilasi
    inline, bukan scope Livewire baru.

    Prop:
     - options  : array<string, string> pasangan value => label (mis. ReportStatus::options())
     - field    : nama properti Livewire yang di-set saat chip diklik (mis. 'filterStatus')
     - selected : nilai properti saat ini (untuk menandai chip aktif)
--}}
<div class="flex items-center gap-2 flex-wrap">
    @foreach ($options as $val => $label)
        <button
            wire:click="$set('{{ $field }}', '{{ $val }}')"
            @class([
                'btn btn-sm rounded-full',
                'btn-primary' => $selected === (string) $val,
                'btn-ghost border border-base-300' => $selected !== (string) $val,
            ])
        >{{ $label }}</button>
    @endforeach
</div>
