@props(['title', 'noun', 'name' => null, 'action'])

{{--
    Modal konfirmasi hapus (pola seragam). wire:model & wire:click di-resolve ke
    komponen Livewire induk (Blade component dikompilasi inline, bukan scope baru),
    jadi properti `showDeleteModal` & method hapus tetap milik induk. Dipakai bersama
    halaman Laporan & Pengguna.

    Prop:
     - title  : judul modal (mis. "Hapus Laporan")
     - noun   : kata benda objek dalam kalimat (mis. "laporan", "pengguna")
     - name   : nama objek yang akan dihapus (properti $deletingName induk)
     - action : nama method Livewire induk yang menghapus (mis. "deleteReport")
--}}
<x-mary-modal wire:model="showDeleteModal" :title="$title" separator>
    <p class="text-sm text-base-content/70">
        Yakin ingin menghapus {{ $noun }}
        <strong class="text-base-content">{{ $name }}</strong>?
        Tindakan ini tidak dapat dibatalkan.
    </p>

    <x-slot:actions>
        <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showDeleteModal', false)" />
        <x-mary-button label="Ya, Hapus" class="btn-error rounded-full" wire:click="{{ $action }}" spinner="{{ $action }}" />
    </x-slot:actions>
</x-mary-modal>
