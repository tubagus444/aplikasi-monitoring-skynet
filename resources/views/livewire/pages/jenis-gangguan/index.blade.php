<?php

use App\Livewire\Concerns\WithTableFilters;
use App\Models\DamageType;
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
    public string $description = '';

    #[Computed]
    public function types()
    {
        return DamageType::withCount('damageReports')
            ->when($this->search, fn ($q) =>
                $q->where(fn ($w) =>
                    $w->where('name', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%")
                )
            )
            ->orderBy('name')
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
        $type = DamageType::findOrFail($id);
        $this->editingId = $id;
        $this->name = $type->name;
        $this->description = $type->description ?? '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:255|unique:damage_types,name' . ($this->editingId ? ",{$this->editingId}" : ''),
            'description' => 'nullable|string|max:255',
        ]);

        DamageType::updateOrCreate(
            ['id' => $this->editingId],
            ['name' => $this->name, 'description' => $this->description ?: null],
        );

        $this->showFormModal = false;
        unset($this->types);
        $this->success($this->editingId ? 'Jenis gangguan berhasil diperbarui.' : 'Jenis gangguan baru berhasil ditambahkan.');
        $this->resetForm();
        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $type = DamageType::withCount('damageReports')->find($id);
        if (! $type) {
            return;
        }
        $this->deletingId = $id;
        $this->deletingName = $type->name;
        $this->deletingUsage = $type->damage_reports_count;
        $this->showDeleteModal = true;
    }

    public function deleteType(): void
    {
        // FK `nullOnDelete`: laporan yang memakai jenis ini TIDAK ikut terhapus —
        // kolom damage_type_id-nya jadi NULL (tampil "—"). Selaras prinsip arsip.
        DamageType::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
        $this->deletingUsage = 0;
        unset($this->types);
        $this->success('Jenis gangguan berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->description = '';
        $this->resetValidation();
    }

    protected function tableComputed(): string|array
    {
        return 'types';
    }
}; ?>

<div>
    <x-mary-header title="Jenis Gangguan/Pekerjaan" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button
                icon="o-plus"
                label="Tambah Jenis"
                class="btn-primary btn-sm rounded-full"
                wire:click="openCreate"
            />
        </x-slot:actions>
    </x-mary-header>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari jenis gangguan..."
            icon="o-magnifying-glass"
            class="input-sm w-56 rounded-full"
        />
    </div>

    {{-- Tabel --}}
    <x-table-card :rows="$this->types" empty-icon="o-wrench-screwdriver" empty-text="Belum ada jenis gangguan">
        <x-slot:head>
            <th class="w-12">#</th>
            <th>Nama</th>
            <th>Deskripsi</th>
            <th class="w-32">Dipakai</th>
            <th class="w-20">Aksi</th>
        </x-slot:head>

        @foreach($this->types as $type)
            <tr class="hover:bg-base-200 transition-colors">
                <td class="text-base-content/40 text-xs">{{ $type->id }}</td>
                <td class="font-medium text-sm">{{ $type->name }}</td>
                <td class="text-sm text-base-content/70 max-w-md truncate">
                    {{ $type->description ?: '—' }}
                </td>
                <td>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $type->damage_reports_count > 0 ? 'bg-info/15 text-info' : 'bg-base-content/10 text-base-content/50' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $type->damage_reports_count > 0 ? 'bg-info' : 'bg-base-content/40' }}"></span>
                        {{ $type->damage_reports_count }} laporan
                    </span>
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-mary-button
                            icon="o-pencil"
                            class="btn-ghost btn-xs rounded-full"
                            wire:click="openEdit({{ $type->id }})"
                            tooltip="Edit"
                        />
                        <x-mary-button
                            icon="o-trash"
                            class="btn-ghost btn-xs text-error rounded-full"
                            wire:click="confirmDelete({{ $type->id }})"
                            tooltip="Hapus"
                        />
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table-card>

    {{-- Modal Buat / Edit Jenis Gangguan --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Jenis Gangguan' : 'Tambah Jenis Gangguan'" separator>
        <div class="space-y-4">
            <x-mary-input
                label="Nama"
                wire:model="name"
                placeholder="mis. Konektor Rusak"
                icon="o-wrench-screwdriver"
                required
            />
            <x-textarea
                label="Deskripsi"
                wire:model="description"
                placeholder="Penjelasan singkat jenis gangguan/pekerjaan (opsional)..."
                rows="3"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Tambah Jenis'"
                class="btn-primary rounded-full"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Jenis Gangguan" separator>
        <p class="text-sm text-base-content/70">
            Yakin ingin menghapus jenis gangguan
            <strong class="text-base-content">{{ $deletingName }}</strong>?
        </p>
        @if($deletingUsage > 0)
            <div class="mt-3 flex items-start gap-2 p-3 rounded-xl bg-warning/10 text-warning-content/80 text-xs">
                <x-mary-icon name="o-information-circle" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                <span>
                    Dipakai oleh <strong>{{ $deletingUsage }} laporan</strong>. Laporan tetap aman —
                    kolom jenisnya akan menjadi kosong (tampil "—"), bukan ikut terhapus.
                </span>
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus" class="btn-error rounded-full" wire:click="deleteType" spinner="deleteType" />
        </x-slot:actions>
    </x-mary-modal>
</div>
