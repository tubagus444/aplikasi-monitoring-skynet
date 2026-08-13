<?php

use App\Livewire\Concerns\WithTableFilters;
use App\Models\InternetPackage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Toast, WithTableFilters;

    // Filter & search
    public string $search = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $deletingId = null;
    public ?string $deletingName = null;
    public int $deletingUsage = 0;

    // Form fields
    public string $name = '';
    public string $speed_mbps = '';
    public ?string $price = '';
    public ?string $description = '';

    #[Computed]
    public function packages()
    {
        return InternetPackage::withCount('customers')
            ->when($this->search, fn ($q) =>
                $q->where(fn ($w) =>
                    $w->where('name', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%")
                )
            )
            ->orderBy('speed_mbps')
            ->paginate(10);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $pkg = InternetPackage::findOrFail($id);
        $this->editingId = $id;
        $this->name = $pkg->name;
        $this->speed_mbps = (string) $pkg->speed_mbps;
        $this->price = $pkg->price !== null ? (string) $pkg->price : '';
        $this->description = $pkg->description ?? '';
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        // Normalisasi field opsional: '' → null
        if ($this->price === '') {
            $this->price = null;
        }
        if ($this->description === '') {
            $this->description = null;
        }

        $data = $this->validate([
            'name'        => 'required|string|max:255|unique:internet_packages,name' . ($this->editingId ? ",{$this->editingId}" : ''),
            'speed_mbps'  => 'required|integer|min:1|max:10000',
            'price'       => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        InternetPackage::updateOrCreate(
            ['id' => $this->editingId],
            $data,
        );

        $this->showFormModal = false;
        unset($this->packages);
        $this->success($this->editingId ? 'Paket internet berhasil diperbarui.' : 'Paket internet baru berhasil ditambahkan.');
        $this->resetForm();
        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $pkg = InternetPackage::withCount('customers')->find($id);
        if (! $pkg) {
            return;
        }
        $this->deletingId = $id;
        $this->deletingName = $pkg->name;
        $this->deletingUsage = $pkg->customers_count;
        $this->showDeleteModal = true;
    }

    public function deletePackage(): void
    {
        // FK `nullOnDelete`: pelanggan yang memakai paket ini TIDAK ikut terhapus —
        // kolom internet_package_id-nya jadi NULL (tampil "—"). Selaras prinsip arsip.
        InternetPackage::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
        $this->deletingUsage = 0;
        unset($this->packages);
        $this->success('Paket internet berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->speed_mbps = '';
        $this->price = '';
        $this->description = '';
        $this->resetValidation();
    }

    protected function tableComputed(): string|array
    {
        return 'packages';
    }
}; ?>

<div>
    <x-mary-header title="Paket Internet" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button
                icon="o-plus"
                label="Tambah Paket"
                class="btn-primary btn-sm rounded-full"
                wire:click="openCreate"
            />
        </x-slot:actions>
    </x-mary-header>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari paket internet..."
            icon="o-magnifying-glass"
            class="input-sm w-56 rounded-full"
        />
    </div>

    {{-- Tabel --}}
    <x-table-card :rows="$this->packages" empty-icon="o-wifi" empty-text="Belum ada paket internet">
        <x-slot:head>
            <th class="w-12">#</th>
            <th>Nama Paket</th>
            <th>Kecepatan</th>
            <th>Harga/Bulan</th>
            <th>Deskripsi</th>
            <th class="w-32">Dipakai</th>
            <th class="w-20">Aksi</th>
        </x-slot:head>

        @foreach($this->packages as $pkg)
            <tr class="hover:bg-base-200 transition-colors">
                <td class="text-base-content/40 text-xs">{{ $pkg->id }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-primary/10 text-primary">
                            <x-mary-icon name="o-wifi" class="w-4 h-4" />
                        </span>
                        <span class="font-medium text-sm">{{ $pkg->name }}</span>
                    </div>
                </td>
                <td class="text-sm text-base-content/70">
                    {{ $pkg->speed_mbps }} Mbps
                </td>
                <td class="text-sm text-base-content/70">
                    {{ $pkg->price !== null ? 'Rp ' . number_format($pkg->price, 0, ',', '.') : '—' }}
                </td>
                <td class="text-sm text-base-content/70 max-w-xs truncate">
                    {{ $pkg->description ?: '—' }}
                </td>
                <td>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $pkg->customers_count > 0 ? 'bg-info/15 text-info' : 'bg-base-content/10 text-base-content/50' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $pkg->customers_count > 0 ? 'bg-info' : 'bg-base-content/40' }}"></span>
                        {{ $pkg->customers_count }} pelanggan
                    </span>
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-mary-button
                            icon="o-pencil"
                            class="btn-ghost btn-xs rounded-full"
                            wire:click="openEdit({{ $pkg->id }})"
                            tooltip="Edit"
                        />
                        <x-mary-button
                            icon="o-trash"
                            class="btn-ghost btn-xs text-error rounded-full"
                            wire:click="confirmDelete({{ $pkg->id }})"
                            tooltip="Hapus"
                        />
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table-card>

    {{-- Modal Buat / Edit Paket Internet --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Paket Internet' : 'Tambah Paket Internet'" separator>
        <div class="space-y-4">
            <x-mary-input
                label="Nama Paket"
                wire:model="name"
                placeholder="mis. 20 Mbps"
                icon="o-wifi"
                required
            />
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input
                    label="Kecepatan (Mbps)"
                    wire:model="speed_mbps"
                    placeholder="mis. 20"
                    icon="o-bolt"
                    type="number"
                    required
                />
                <x-mary-input
                    label="Harga / Bulan (Rp)"
                    wire:model="price"
                    placeholder="mis. 150000 (opsional)"
                    icon="o-banknotes"
                    type="number"
                />
            </div>
            <x-textarea
                label="Deskripsi"
                wire:model="description"
                placeholder="Keterangan paket (opsional)..."
                rows="2"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Tambah Paket'"
                class="btn-primary rounded-full"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Paket Internet" separator>
        <p class="text-sm text-base-content/70">
            Yakin ingin menghapus paket
            <strong class="text-base-content">{{ $deletingName }}</strong>?
        </p>
        @if($deletingUsage > 0)
            <div class="mt-3 flex items-start gap-2 p-3 rounded-xl bg-warning/10 text-warning-content/80 text-xs">
                <x-mary-icon name="o-information-circle" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                <span>
                    Dipakai oleh <strong>{{ $deletingUsage }} pelanggan</strong>. Data pelanggan tetap aman —
                    kolom paketnya akan menjadi kosong (tampil "—"), bukan ikut terhapus.
                </span>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus" class="btn-error rounded-full" wire:click="deletePackage" spinner="deletePackage" />
        </x-slot:actions>
    </x-mary-modal>
</div>
