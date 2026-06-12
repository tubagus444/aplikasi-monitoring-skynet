<?php

use App\Actions\SyncReportTechnicians;
use App\Enums\ReportStatus;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Toast;

    // Filter & search
    public string $search = '';
    public string $filterStatus = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $deletingId = null;

    // Form fields
    public string $customer_name = '';
    public string $address = '';
    public int|string $damage_type_id = '';
    public string $notes = '';
    public array $selectedTechnicians = [];

    #[Computed]
    public function reports()
    {
        return DamageReport::with(['damageType', 'taskAssignments.technician'])
            ->when($this->search, fn($q) =>
                $q->where(fn($w) =>
                    $w->where('customer_name', 'like', "%{$this->search}%")
                      ->orWhere('address', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterStatus, fn($q) =>
                $q->where('status', $this->filterStatus)
            )
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function damageTypes()
    {
        return DamageType::orderBy('name')->get();
    }

    #[Computed]
    public function technicians()
    {
        return User::where('role', 'teknisi')->orderBy('name')->get();
    }

    /** Opsi chip filter status: 'Semua' + seluruh status dari enum. */
    #[Computed]
    public function statusOptions(): array
    {
        return ['' => 'Semua'] + ReportStatus::options();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $report = DamageReport::with('taskAssignments')->findOrFail($id);
        $this->editingId = $id;
        $this->customer_name = $report->customer_name;
        $this->address = $report->address;
        $this->damage_type_id = $report->damage_type_id;
        $this->notes = $report->notes ?? '';
        $this->selectedTechnicians = $report->taskAssignments->pluck('technician_id')->toArray();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'customer_name'  => 'required|string|max:255',
            'address'        => 'required|string|max:255',
            'damage_type_id' => 'required|exists:damage_types,id',
            'notes'          => 'nullable|string',
        ]);

        if ($this->editingId) {
            $report = DamageReport::findOrFail($this->editingId);
            $report->update([
                'customer_name'  => $this->customer_name,
                'address'        => $this->address,
                'damage_type_id' => $this->damage_type_id,
                'notes'          => $this->notes ?: null,
            ]);
        } else {
            $report = DamageReport::create([
                'created_by'     => auth()->id(),
                'customer_name'  => $this->customer_name,
                'address'        => $this->address,
                'damage_type_id' => $this->damage_type_id,
                'notes'          => $this->notes ?: null,
                'status'         => ReportStatus::Ditugaskan->value,
            ]);
        }

        // Sinkronkan penugasan teknisi + notifikasi teknisi baru (sumber tunggal)
        (new SyncReportTechnicians)($report, $this->selectedTechnicians);

        $this->showFormModal = false;
        unset($this->reports);
        $this->success($this->editingId ? 'Laporan berhasil diperbarui.' : 'Laporan baru berhasil dibuat.');
        $this->resetForm();
        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteReport(): void
    {
        DamageReport::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->reports);
        $this->success('Laporan berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->customer_name = '';
        $this->address = '';
        $this->damage_type_id = '';
        $this->notes = '';
        $this->selectedTechnicians = [];
        $this->resetValidation();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->reports);
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        unset($this->reports);
    }
}; ?>

<div>
    {{-- Header --}}
    <x-mary-header title="Manajemen Laporan" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button
                icon="o-plus"
                label="Buat Laporan"
                class="btn-primary btn-sm rounded-full"
                wire:click="openCreate"
            />
        </x-slot:actions>
    </x-mary-header>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari pelanggan atau alamat..."
            icon="o-magnifying-glass"
            class="input-sm w-56 rounded-full"
        />
        <div class="flex items-center gap-2 flex-wrap">
            @foreach ($this->statusOptions as $val => $label)
                <button
                    wire:click="$set('filterStatus', '{{ $val }}')"
                    @class([
                        'btn btn-sm rounded-full',
                        'btn-primary' => $filterStatus === $val,
                        'btn-ghost border border-base-300' => $filterStatus !== $val,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Tabel --}}
    <x-mary-card class="rounded-2xl">
        @if($this->reports->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-mary-icon name="o-document-text" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="text-sm">Tidak ada laporan ditemukan</p>
            </div>
        @else
            <div class="overflow-x-auto no-scrollbar">
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="text-xs text-base-content/50 uppercase">
                            <th class="w-12">#</th>
                            <th>Pelanggan</th>
                            <th>Alamat</th>
                            <th>Jenis Gangguan</th>
                            <th>Teknisi</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th class="w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->reports as $report)
                            <tr class="hover:bg-base-200 transition-colors">
                                <td class="text-base-content/40 text-xs">{{ $report->id }}</td>
                                <td class="font-medium">{{ $report->customer_name }}</td>
                                <td class="text-sm text-base-content/70 max-w-[160px] truncate">{{ $report->address }}</td>
                                <td class="text-sm">{{ $report->damageType->name }}</td>
                                <td class="text-sm">
                                    @if($report->taskAssignments->isNotEmpty())
                                        {{ $report->taskAssignments->pluck('technician.name')->join(', ') }}
                                    @else
                                        <span class="text-base-content/30 italic">Belum</span>
                                    @endif
                                </td>
                                <td>
                                    <x-status-pill :status="$report->status" />
                                </td>
                                <td class="text-xs text-base-content/50">
                                    {{ $report->created_at->format('d/m/Y') }}
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <x-mary-button
                                            icon="o-pencil"
                                            class="btn-ghost btn-xs rounded-full"
                                            wire:click="openEdit({{ $report->id }})"
                                            tooltip="Edit"
                                        />
                                        <x-mary-button
                                            icon="o-trash"
                                            class="btn-ghost btn-xs text-error rounded-full"
                                            wire:click="confirmDelete({{ $report->id }})"
                                            tooltip="Hapus"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mary-pagination :rows="$this->reports" class="mt-4" />
        @endif
    </x-mary-card>

    {{-- Modal Buat / Edit Laporan --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Laporan' : 'Buat Laporan Baru'" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-mary-input
                label="Nama Pelanggan"
                wire:model="customer_name"
                placeholder="Pak Budi Santoso"
                icon="o-user"
                required
            />
            <x-mary-input
                label="Alamat"
                wire:model="address"
                placeholder="Jl. Melati No.4, RT 03/RW 05"
                icon="o-map-pin"
                required
            />
        </div>

        <div class="mt-4">
            <x-select
                label="Jenis Gangguan"
                wire:model="damage_type_id"
                :options="$this->damageTypes"
                option-value="id"
                option-label="name"
                placeholder="Pilih jenis gangguan..."
                icon="o-wrench-screwdriver"
                required
            />
        </div>

        <div class="mt-4">
            <x-textarea
                label="Keterangan Tambahan"
                wire:model="notes"
                placeholder="Deskripsi lebih lanjut tentang gangguan..."
                rows="3"
            />
        </div>

        <div class="mt-4">
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-base-content/70">
                    Tugaskan Teknisi <span class="text-base-content/40 font-normal">(opsional)</span>
                </label>
                @if(count($selectedTechnicians) > 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/15 text-primary">
                        {{ count($selectedTechnicians) }} dipilih
                    </span>
                @endif
            </div>

            @if($this->technicians->isEmpty())
                <div class="text-center py-6 rounded-xl border border-dashed border-base-300 text-sm text-base-content/40">
                    Belum ada teknisi terdaftar
                </div>
            @else
                {{-- Kartu pilihan: has-[:checked] me-highlight kartu via CSS murni (instan, tanpa round-trip) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto no-scrollbar pr-0.5">
                    @foreach($this->technicians as $tech)
                        <label class="flex items-center gap-3 cursor-pointer p-2.5 rounded-xl border border-base-300 hover:bg-base-200/60 transition-colors has-checked:border-primary has-checked:bg-primary/5">
                            <input
                                type="checkbox"
                                wire:model="selectedTechnicians"
                                value="{{ $tech->id }}"
                                class="checkbox checkbox-sm checkbox-primary"
                            />
                            <div class="avatar avatar-placeholder shrink-0">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <span class="text-xs font-bold">{{ strtoupper(substr($tech->name, 0, 2)) }}</span>
                                </div>
                            </div>
                            <span class="text-sm font-medium truncate">{{ $tech->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Simpan Laporan'"
                class="btn-primary rounded-full"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Laporan" separator>
        <p class="text-sm text-base-content/70">
            Yakin ingin menghapus laporan
            <strong class="text-base-content">
                {{ $deletingId ? DamageReport::find($deletingId)?->customer_name : '' }}
            </strong>?
            Tindakan ini tidak dapat dibatalkan.
        </p>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus" class="btn-error rounded-full" wire:click="deleteReport" spinner="deleteReport" />
        </x-slot:actions>
    </x-mary-modal>
</div>
