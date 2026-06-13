<?php

namespace App\Livewire\Concerns;

/**
 * Reset paginasi + invalidasi cache computed tabel saat search/filter berubah.
 *
 * Menggantikan boilerplate updatedSearch()/updatedFilterX() yang dulu diulang di
 * halaman Laporan/Pengguna/Riwayat. Memakai lifecycle hook generik `updated()`:
 * begitu properti `search` atau properti berawalan `filter` berubah, halaman
 * kembali ke 1 (resetPage) dan computed tabel di-unset agar dihitung ulang dengan
 * kriteria baru.
 *
 * Syarat komponen pemakai:
 *  - memakai trait Livewire\WithPagination (untuk resetPage()).
 *  - mendeklarasikan tableComputed() = nama #[Computed] tabel (string, atau array
 *    bila lebih dari satu) yang perlu di-unset.
 *
 * Konvensi nama properti filter = `search` + prefix `filter` (filterStatus,
 * filterRole, filterPeriod) — sudah dipakai seragam di seluruh panel admin.
 */
trait WithTableFilters
{
    public function updated($name): void
    {
        if ($name !== 'search' && ! str_starts_with($name, 'filter')) {
            return;
        }

        $this->resetPage();

        foreach ((array) $this->tableComputed() as $computed) {
            unset($this->{$computed});
        }
    }

    /**
     * Nama #[Computed] tabel yang di-unset saat search/filter berubah.
     *
     * @return string|array<int, string>
     */
    abstract protected function tableComputed(): string|array;
}
