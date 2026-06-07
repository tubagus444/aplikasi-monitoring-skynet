<?php

use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\Notification;
use App\Models\TaskAssignment;
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
                $q->where('customer_name', 'like', "%{$this->search}%")
                  ->orWhere('address', 'like', "%{$this->search}%")
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

            // Sync teknisi
            $existingTechIds = $report->taskAssignments()->pluck('technician_id')->toArray();
            $report->taskAssignments()->delete();
            foreach ($this->selectedTechnicians as $techId) {
                TaskAssignment::create([
                    'report_id'      => $report->id,
                    'technician_id'  => $techId,
                ]);
            }

            // Notifikasi hanya ke teknisi yang baru ditambahkan
            foreach (array_diff($this->selectedTechnicians, $existingTechIds) as $techId) {
                Notification::create([
                    'user_id' => $techId,
                    'title'   => 'Tugas Baru Ditugaskan',
                    'body'    => "Anda ditugaskan untuk menangani laporan gangguan di {$this->address} atas nama pelanggan {$this->customer_name}.",
                ]);
            }
        } else {
            $report = DamageReport::create([
                'created_by'     => auth()->id(),
                'customer_name'  => $this->customer_name,
                'address'        => $this->address,
                'damage_type_id' => $this->damage_type_id,
                'notes'          => $this->notes ?: null,
                'status'         => 'ditugaskan',
            ]);

            foreach ($this->selectedTechnicians as $techId) {
                TaskAssignment::create([
                    'report_id'     => $report->id,
                    'technician_id' => $techId,
                ]);
                Notification::create([
                    'user_id' => $techId,
                    'title'   => 'Tugas Baru Ditugaskan',
                    'body'    => "Anda ditugaskan untuk menangani laporan gangguan di {$this->address} atas nama pelanggan {$this->customer_name}.",
                ]);
            }
        }

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
            @foreach (['' => 'Semua', 'ditugaskan' => 'Ditugaskan', 'sedang_memperbaiki' => 'Sedang Memperbaiki', 'selesai' => 'Selesai'] as $val => $label)
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
                                    @php
                                        // Pil status: latar soft (opacity v4) + titik warna — selaras dengan dashboard.
                                        // Kelas ditulis literal lengkap agar terdeteksi scanner Tailwind.
                                        $status = match($report->status) {
                                            'ditugaskan'        => ['pill' => 'bg-warning/15 text-warning', 'dot' => 'bg-warning',          'label' => 'Ditugaskan'],
                                            'sedang_memperbaiki'=> ['pill' => 'bg-info/15 text-info',       'dot' => 'bg-info',             'label' => 'Memperbaiki'],
                                            'selesai'           => ['pill' => 'bg-success/15 text-success', 'dot' => 'bg-success',          'label' => 'Selesai'],
                                            default             => ['pill' => 'bg-base-200 text-base-content/60', 'dot' => 'bg-base-content/40', 'label' => $report->status],
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $status['pill'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>
                                        {{ $status['label'] }}
                                    </span>
                                </td>
                                <td class="text-xs text-base-content/50">
                                    {{ $report->created_at->format('d/m/Y') }}
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <x-mary-button
                                            icon="o-pencil"
                                            class="btn-ghost btn-xs"
                                            wire:click="openEdit({{ $report->id }})"
                                            tooltip="Edit"
                                        />
                                        <x-mary-button
                                            icon="o-trash"
                                            class="btn-ghost btn-xs text-error"
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
                required
            />
            <x-mary-input
                label="Alamat"
                wire:model="address"
                placeholder="Jl. Melati No.4, RT 03/RW 05"
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
            <label class="block text-sm font-medium text-base-content/70 mb-2">
                Tugaskan Teknisi <span class="text-base-content/40 font-normal">(opsional)</span>
            </label>
            <div class="flex flex-col gap-2">
                @foreach($this->technicians as $tech)
                    <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-base-200 transition-colors">
                        <input
                            type="checkbox"
                            wire:model="selectedTechnicians"
                            value="{{ $tech->id }}"
                            class="checkbox checkbox-sm checkbox-primary"
                        />
                        <span class="text-sm font-medium">{{ $tech->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Simpan Laporan'"
                class="btn-primary"
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
            <x-mary-button label="Batal" class="btn-ghost" wire:click="$set('showDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus" class="btn-error" wire:click="deleteReport" spinner="deleteReport" />
        </x-slot:actions>
    </x-mary-modal>
</div>
