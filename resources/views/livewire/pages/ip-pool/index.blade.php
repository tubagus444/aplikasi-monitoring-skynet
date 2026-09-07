<?php

use App\Enums\IpPoolStatus;
use App\Livewire\Concerns\WithTableFilters;
use App\Models\IpPool;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Toast, WithTableFilters;

    // Filter & Search
    public string $search = '';
    public string $filterStatus = '';
    public string $filterSegment = '';

    // Modal Single IP
    public bool $showFormModal = false;
    public ?int $editingId = null;
    public string $ip_address = '';
    public string $segment = '';
    public string $status = '';
    public ?string $notes = '';

    // Modal Batch Generator
    public bool $showBatchModal = false;
    public string $start_ip = '';
    public string $end_ip = '';
    public string $batch_segment = '';
    public ?string $batch_notes = '';

    // Modal Delete
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;
    public ?string $deletingIp = null;

    public function mount(): void
    {
        $this->status = IpPoolStatus::Tersedia->value;
    }

    #[Computed]
    public function stats(): array
    {
        $all = IpPool::select('status')->get();

        return [
            'total'    => $all->count(),
            'tersedia' => $all->where('status', IpPoolStatus::Tersedia->value)->count(),
            'terpakai' => $all->where('status', IpPoolStatus::Terpakai->value)->count(),
            'reserved' => $all->where('status', IpPoolStatus::Reserved->value)->count(),
        ];
    }

    #[Computed]
    public function segments(): array
    {
        return IpPool::whereNotNull('segment')
            ->where('segment', '!=', '')
            ->distinct()
            ->orderBy('segment')
            ->pluck('segment')
            ->toArray();
    }

    #[Computed]
    public function ipPools()
    {
        return IpPool::with('customer')
            ->filtered($this->search, $this->filterStatus, $this->filterSegment)
            ->paginate(15);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->status = IpPoolStatus::Tersedia->value;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $pool = IpPool::with('customer')->findOrFail($id);
        $this->editingId = $id;
        $this->ip_address = $pool->ip_address;
        $this->segment = $pool->segment ?? '';
        $this->status = $pool->status;
        $this->notes = $pool->notes ?? '';
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $rules = [
            'ip_address' => [
                'required',
                'ipv4',
                'unique:ip_pools,ip_address' . ($this->editingId ? ",{$this->editingId}" : ''),
            ],
            'segment' => 'nullable|string|max:50',
            'status'  => 'required|in:' . implode(',', IpPoolStatus::values()),
            'notes'   => 'nullable|string|max:255',
        ];

        // Jika IP sedang terpakai oleh pelanggan, status tidak boleh diubah manual dari modal ini
        if ($this->editingId) {
            $pool = IpPool::findOrFail($this->editingId);
            if ($pool->status === IpPoolStatus::Terpakai->value) {
                unset($rules['status']);
            }
        }

        $validated = $this->validate($rules);

        $data = [
            'ip_address' => $validated['ip_address'],
            'segment'    => $this->segment !== '' ? $this->segment : null,
            'notes'      => $this->notes !== '' ? $this->notes : null,
        ];

        if (isset($validated['status'])) {
            $data['status'] = $validated['status'];
        }

        if ($this->editingId) {
            IpPool::findOrFail($this->editingId)->update($data);
        } else {
            IpPool::create($data);
        }

        $this->showFormModal = false;
        unset($this->ipPools, $this->stats, $this->segments);
        $this->success($this->editingId ? 'Alamat IP berhasil diperbarui.' : 'Alamat IP baru berhasil ditambahkan.');
        $this->resetForm();
    }

    public function openBatch(): void
    {
        $this->start_ip = '';
        $this->end_ip = '';
        $this->batch_segment = '';
        $this->batch_notes = '';
        $this->resetValidation();
        $this->showBatchModal = true;
    }

    public function generateBatch(): void
    {
        $this->validate([
            'start_ip'      => 'required|ipv4',
            'end_ip'        => 'required|ipv4',
            'batch_segment' => 'nullable|string|max:50',
            'batch_notes'   => 'nullable|string|max:255',
        ], [
            'start_ip.required' => 'IP Awal wajib diisi.',
            'start_ip.ipv4'     => 'Format IP Awal harus berupa IPv4 yang valid.',
            'end_ip.required'   => 'IP Akhir wajib diisi.',
            'end_ip.ipv4'       => 'Format IP Akhir harus berupa IPv4 yang valid.',
        ]);

        $startLong = ip2long($this->start_ip);
        $endLong = ip2long($this->end_ip);

        if ($startLong > $endLong) {
            $this->addError('start_ip', 'IP Awal tidak boleh lebih besar dari IP Akhir.');
            return;
        }

        $totalCount = ($endLong - $startLong) + 1;
        if ($totalCount > 1024) {
            $this->addError('end_ip', 'Rentang IP terlalu besar (maksimal 1.024 IP per proses).');
            return;
        }

        $rows = [];
        $now = now();
        $segment = $this->batch_segment !== '' ? $this->batch_segment : null;
        $notes = $this->batch_notes !== '' ? $this->batch_notes : null;

        for ($current = $startLong; $current <= $endLong; $current++) {
            $rows[] = [
                'ip_address'  => long2ip($current),
                'segment'     => $segment,
                'status'      => IpPoolStatus::Tersedia->value,
                'customer_id' => null,
                'notes'       => $notes,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        // Simpan dalam batch chunks menggunakan insertOrIgnore agar duplikasi terlewati aman
        $insertedCount = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $insertedCount += DB::table('ip_pools')->insertOrIgnore($chunk);
        }

        $this->showBatchModal = false;
        unset($this->ipPools, $this->stats, $this->segments);
        $this->success("Berhasil menambahkan {$insertedCount} alamat IP ke dalam pool.");
    }

    public function confirmDelete(int $id): void
    {
        $pool = IpPool::find($id);
        if (! $pool) {
            return;
        }

        if ($pool->status === IpPoolStatus::Terpakai->value) {
            $this->error('IP ini sedang aktif digunakan oleh pelanggan dan tidak dapat dihapus.');
            return;
        }

        $this->deletingId = $id;
        $this->deletingIp = $pool->ip_address;
        $this->showDeleteModal = true;
    }

    public function deleteIp(): void
    {
        $pool = IpPool::findOrFail($this->deletingId);

        if ($pool->status === IpPoolStatus::Terpakai->value) {
            $this->error('IP ini sedang aktif digunakan oleh pelanggan dan tidak dapat dihapus.');
            return;
        }

        $pool->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingIp = null;
        unset($this->ipPools, $this->stats, $this->segments);
        $this->success('Alamat IP berhasil dihapus dari pool.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->ip_address = '';
        $this->segment = '';
        $this->status = IpPoolStatus::Tersedia->value;
        $this->notes = '';
        $this->resetValidation();
    }

    protected function tableComputed(): string|array
    {
        return 'ipPools';
    }
}; ?>

<div>
    {{-- Header --}}
    <x-mary-header title="IP Pool" subtitle="Manajemen inventaris alamat IP dan alokasi pelanggan SkyNet" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button
                icon="o-bolt"
                label="Generate Rentang IP"
                class="btn-sm rounded-full bg-primary/10 hover:bg-primary/20 text-primary border-0 font-medium"
                wire:click="openBatch"
            />
            <x-mary-button
                icon="o-plus"
                label="Tambah IP"
                class="btn-primary btn-sm rounded-full"
                wire:click="openCreate"
            />
        </x-slot:actions>
    </x-mary-header>

    {{-- Kartu Metrik Statistik --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="card bg-base-100 border border-base-200 p-4 rounded-2xl shadow-xs">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-base-200 rounded-xl">
                    <x-mary-icon name="o-circle-stack" class="w-5 h-5 text-base-content/70" />
                </div>
                <div>
                    <p class="text-xs text-base-content/50 font-medium">Total IP</p>
                    <p class="text-xl font-bold font-mono">{{ number_format($this->stats['total']) }}</p>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-200 p-4 rounded-2xl shadow-xs">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-success/10 rounded-xl">
                    <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success" />
                </div>
                <div>
                    <p class="text-xs text-base-content/50 font-medium">Tersedia</p>
                    <p class="text-xl font-bold font-mono text-success">{{ number_format($this->stats['tersedia']) }}</p>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-200 p-4 rounded-2xl shadow-xs">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-info/10 rounded-xl">
                    <x-mary-icon name="o-user-group" class="w-5 h-5 text-info" />
                </div>
                <div>
                    <p class="text-xs text-base-content/50 font-medium">Terpakai</p>
                    <p class="text-xl font-bold font-mono text-info">{{ number_format($this->stats['terpakai']) }}</p>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-200 p-4 rounded-2xl shadow-xs">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-warning/10 rounded-xl">
                    <x-mary-icon name="o-shield-check" class="w-5 h-5 text-warning" />
                </div>
                <div>
                    <p class="text-xs text-base-content/50 font-medium">Reserved</p>
                    <p class="text-xl font-bold font-mono text-warning">{{ number_format($this->stats['reserved']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari IP, segmen, pelanggan, catatan..."
            icon="o-magnifying-glass"
            class="input-sm w-64 rounded-full"
        />

        <x-filter-chips
            :options="['' => 'Semua'] + \App\Enums\IpPoolStatus::options()"
            field="filterStatus"
            :selected="$filterStatus"
        />

        @if(count($this->segments) > 0)
            <select
                wire:model.live="filterSegment"
                class="select select-sm rounded-full border-base-300 max-w-xs text-xs"
            >
                <option value="">Semua Segmen</option>
                @foreach($this->segments as $seg)
                    <option value="{{ $seg }}">{{ $seg }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- Tabel IP Pool --}}
    <x-table-card :rows="$this->ipPools" empty-icon="o-circle-stack" empty-text="Tidak ada alamat IP ditemukan">
        <x-slot:head>
            <th class="w-36">Alamat IP</th>
            <th>Segmen / Subnet</th>
            <th class="w-32">Status</th>
            <th>Pelanggan Terkait</th>
            <th>Catatan</th>
            <th class="w-20 text-center">Aksi</th>
        </x-slot:head>

        @foreach($this->ipPools as $pool)
            @php
                $statusEnum = \App\Enums\IpPoolStatus::tryFrom($pool->status);
                $style = $statusEnum?->style() ?? ['pill' => 'bg-base-200 text-base-content/60', 'dot' => 'bg-base-content/40'];
                $isTerpakai = $pool->status === \App\Enums\IpPoolStatus::Terpakai->value;
            @endphp
            <tr class="hover:bg-base-200/50 transition-colors">
                <td class="font-mono text-xs font-semibold">
                    <span class="px-2 py-0.5 rounded-md bg-base-200/70 border border-base-300/60">
                        {{ $pool->ip_address }}
                    </span>
                </td>
                <td class="text-xs text-base-content/70">
                    {{ $pool->segment ?: '—' }}
                </td>
                <td>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $style['pill'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $style['dot'] }}"></span>
                        {{ $statusEnum?->label() ?? $pool->status }}
                    </span>
                </td>
                <td>
                    @if($pool->customer)
                        <a
                            href="{{ route('customers.show', $pool->customer) }}"
                            wire:navigate
                            class="inline-flex items-center gap-1.5 hover:text-primary transition-colors text-xs font-medium"
                        >
                            <x-mary-icon name="o-user" class="w-3.5 h-3.5 text-base-content/40" />
                            <span>{{ $pool->customer->name }}</span>
                            <span class="font-mono text-2xs text-base-content/50">({{ $pool->customer->customer_code }})</span>
                        </a>
                    @else
                        <span class="text-xs text-base-content/40 italic">—</span>
                    @endif
                </td>
                <td class="text-xs text-base-content/60 max-w-xs truncate" title="{{ $pool->notes }}">
                    {{ $pool->notes ?: '—' }}
                </td>
                <td>
                    <div class="flex items-center justify-center gap-1">
                        <x-mary-button
                            icon="o-pencil"
                            class="btn-ghost btn-xs rounded-full"
                            wire:click="openEdit({{ $pool->id }})"
                            tooltip="Edit"
                        />
                        @if(! $isTerpakai)
                            <x-mary-button
                                icon="o-trash"
                                class="btn-ghost btn-xs text-error rounded-full"
                                wire:click="confirmDelete({{ $pool->id }})"
                                tooltip="Hapus"
                            />
                        @else
                            <button
                                class="btn btn-ghost btn-xs rounded-full opacity-20 cursor-not-allowed"
                                disabled
                                title="Tidak dapat dihapus saat sedang digunakan pelanggan"
                            >
                                <x-mary-icon name="o-trash" class="w-3.5 h-3.5" />
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table-card>

    {{-- Modal Tambah / Edit IP Satuan --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Alamat IP' : 'Tambah Alamat IP Satuan'" separator>
        <div class="space-y-4">
            <x-mary-input
                label="Alamat IP (IPv4)"
                wire:model="ip_address"
                placeholder="192.168.10.15"
                icon="o-globe-alt"
                required
            />

            <x-mary-input
                label="Segmen / Subnet"
                wire:model="segment"
                placeholder="Contoh: Cluster Cibitung atau 192.168.10.0/24 (opsional)"
                icon="o-tag"
            />

            {{-- Pilihan Status jika bukan sedang terpakai --}}
            @if(! $editingId || ($status !== \App\Enums\IpPoolStatus::Terpakai->value))
                <div>
                    <label class="block text-sm font-medium text-base-content/70 mb-2">Status IP</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-base-300 cursor-pointer has-checked:border-success has-checked:bg-success/5 transition-colors">
                            <input type="radio" wire:model="status" value="{{ \App\Enums\IpPoolStatus::Tersedia->value }}" class="radio radio-success radio-sm" />
                            <div>
                                <p class="text-xs font-bold text-success">Tersedia</p>
                                <p class="text-2xs text-base-content/50">Siap dialokasikan ke pelanggan</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-xl border border-base-300 cursor-pointer has-checked:border-warning has-checked:bg-warning/5 transition-colors">
                            <input type="radio" wire:model="status" value="{{ \App\Enums\IpPoolStatus::Reserved->value }}" class="radio radio-warning radio-sm" />
                            <div>
                                <p class="text-xs font-bold text-warning">Reserved</p>
                                <p class="text-2xs text-base-content/50">Gateway / Router / AP tiang</p>
                            </div>
                        </label>
                    </div>
                </div>
            @else
                <div class="alert alert-info py-2 px-3 text-xs rounded-xl flex items-center gap-2">
                    <x-mary-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
                    <span>IP ini sedang aktif digunakan oleh pelanggan. Status akan otomatis berubah saat pelanggan berhenti atau dialokasikan IP lain.</span>
                </div>
            @endif

            <x-textarea
                label="Catatan"
                wire:model="notes"
                placeholder="Catatan tambahan (mis. Gateway Utama RT 03)..."
                rows="2"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" wire:click="$set('showFormModal', false)" class="btn-ghost btn-sm rounded-full" />
            <x-mary-button label="Simpan" wire:click="save" class="btn-primary btn-sm rounded-full" spinner="save" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Batch Generator Rentang IP --}}
    <x-mary-modal wire:model="showBatchModal" title="Generate Rentang Alamat IP" separator>
        <div class="space-y-4">
            <div class="alert alert-info py-2.5 px-3 text-xs rounded-xl flex items-center gap-2">
                <x-mary-icon name="o-bolt" class="w-4 h-4 shrink-0" />
                <span>Sistem akan membuat seluruh alamat IP dalam rentang yang Anda tentukan sekaligus. Alamat IP yang sudah ada sebelumnya tidak akan diduplikasi.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-mary-input
                    label="IP Awal"
                    wire:model="start_ip"
                    placeholder="192.168.10.2"
                    icon="o-arrow-right-start-on-rectangle"
                    hint="IP host pertama"
                    required
                />

                <x-mary-input
                    label="IP Akhir"
                    wire:model="end_ip"
                    placeholder="192.168.10.254"
                    icon="o-arrow-left-end-on-rectangle"
                    hint="IP host terakhir"
                    required
                />
            </div>

            <x-mary-input
                label="Nama Segmen / Subnet (Opsional)"
                wire:model="batch_segment"
                placeholder="Contoh: Cluster Cibitung atau 192.168.10.0/24"
                icon="o-tag"
            />

            <x-mary-input
                label="Catatan Default (Opsional)"
                wire:model="batch_notes"
                placeholder="Catatan untuk seluruh IP dalam rentang ini"
                icon="o-chat-bubble-left-ellipsis"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" wire:click="$set('showBatchModal', false)" class="btn-ghost btn-sm rounded-full" />
            <x-mary-button label="Mulai Generate" wire:click="generateBatch" class="btn-primary btn-sm rounded-full" spinner="generateBatch" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Alamat IP" separator>
        <div class="space-y-3 text-sm">
            <p>Apakah Anda yakin ingin menghapus alamat IP <strong class="font-mono text-primary">{{ $deletingIp }}</strong> dari pool?</p>
            <p class="text-xs text-base-content/60">Tindakan ini tidak dapat dibatalkan. Hanya IP yang tidak sedang terikat ke pelanggan yang dapat dihapus.</p>
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost btn-sm rounded-full" />
            <x-mary-button label="Hapus IP" wire:click="deleteIp" class="btn-error btn-sm rounded-full" spinner="deleteIp" />
        </x-slot:actions>
    </x-mary-modal>
</div>
