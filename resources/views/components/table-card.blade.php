@props([
    'rows',
    'emptyIcon' => 'o-inbox',
    'emptyText' => 'Tidak ada data',
])

{{--
    Kerangka tabel seragam panel admin (hilangkan duplikasi antar halaman
    Laporan/Pengguna/Riwayat): card rounded-2xl → empty-state → wrapper
    overflow-x-auto no-scrollbar → tabel + thead berstyle → pagination.

    Bagian yang berbeda per halaman tetap di pemanggil:
     - slot `head`    : daftar <th> untuk baris header (dibungkus <tr> berstyle di sini)
     - slot default   : baris <tr> isi tabel (mis. hasil @foreach), tanpa <tbody>

    Prop:
     - rows      : paginator (LengthAwarePaginator) — dipakai untuk cek kosong & pagination
     - emptyIcon : nama ikon Heroicons untuk empty-state (mis. 'o-document-text')
     - emptyText : pesan saat tidak ada data
--}}
<x-mary-card class="rounded-2xl">
    @if($rows->isEmpty())
        <div class="text-center py-12 text-base-content/40">
            <x-mary-icon :name="$emptyIcon" class="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p class="text-sm">{{ $emptyText }}</p>
        </div>
    @else
        <div class="overflow-x-auto no-scrollbar">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="text-xs text-base-content/50 uppercase">
                        {{ $head }}
                    </tr>
                </thead>
                <tbody>
                    {{ $slot }}
                </tbody>
            </table>
        </div>
        <x-mary-pagination :rows="$rows" class="mt-4" />
    @endif
</x-mary-card>
