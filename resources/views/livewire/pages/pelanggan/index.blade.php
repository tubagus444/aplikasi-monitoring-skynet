<?php

use App\Enums\CustomerStatus;
use App\Livewire\Concerns\WithTableFilters;
use App\Models\Customer;
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
    public string $filterStatus = '';

    // Toggle tampilan pelanggan terhapus (soft-deleted)
    public bool $showTrashed = false;

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public bool $showForceDeleteModal = false;
    public ?int $editingId = null;
    public ?int $deletingId = null;
    public ?string $deletingName = null;

    // Form fields ('?string' untuk field opsional: dinormalisasi '' → null di save())
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public ?string $ip_address = '';
    public ?int $internet_package_id = null;
    public string $status = '';
    public ?string $latitude = '';
    public ?string $longitude = '';
    public ?string $installed_at = '';

    public function mount(): void
    {
        $this->status = CustomerStatus::Aktif->value;
    }

    #[Computed]
    public function customers()
    {
        // Filter dipusatkan di scope Customer::filtered (dipakai bersama ekspor PDF/Excel).
        $query = Customer::with('internetPackage');

        // Tampilan terhapus: hanya pelanggan soft-deleted
        if ($this->showTrashed) {
            $query->onlyTrashed();
        }

        return $query
            ->filtered($this->search, $this->filterStatus)
            ->paginate(10);
    }

    /** Jumlah pelanggan terhapus — ditampilkan di badge toggle. */
    #[Computed]
    public function trashedCount(): int
    {
        return Customer::onlyTrashed()->count();
    }

    /** Daftar paket internet untuk dropdown form. */
    #[Computed]
    public function packageOptions()
    {
        return InternetPackage::orderBy('speed_mbps')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name . ' — Rp ' . number_format($p->price ?? 0, 0, ',', '.')])
            ->toArray();
    }

    /** Toggle tampilan pelanggan terhapus. */
    public function toggleTrashed(): void
    {
        $this->showTrashed = ! $this->showTrashed;
        $this->resetPage();
        unset($this->customers, $this->trashedCount);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->editingId = $id;
        $this->name = $customer->name;
        $this->phone = $customer->phone;
        $this->address = $customer->address;
        $this->ip_address = $customer->ip_address ?? '';
        $this->internet_package_id = $customer->internet_package_id;
        $this->status = $customer->status;
        $this->latitude = $customer->latitude !== null ? (string) $customer->latitude : '';
        $this->longitude = $customer->longitude !== null ? (string) $customer->longitude : '';
        $this->installed_at = $customer->installed_at?->format('Y-m-d') ?? '';
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        // Normalisasi field opsional: '' (input kosong) → null sebelum validasi/simpan
        // agar aturan nullable|numeric/date tidak menolak string kosong.
        foreach (['ip_address', 'latitude', 'longitude', 'installed_at'] as $opt) {
            if ($this->{$opt} === '') {
                $this->{$opt} = null;
            }
        }

        $data = $this->validate([
            'name'                 => 'required|string|max:255',
            'phone'                => 'required|string|max:30',
            'address'              => 'required|string|max:255',
            'ip_address'           => 'nullable|string|max:45',
            'internet_package_id'  => 'nullable|exists:internet_packages,id',
            'status'               => 'required|in:' . implode(',', CustomerStatus::values()),
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
            'installed_at'         => 'nullable|date',
        ]);

        if ($this->editingId) {
            Customer::findOrFail($this->editingId)->update($data);
        } else {
            Customer::create($data);
        }

        $this->showFormModal = false;
        unset($this->customers);
        $this->success($this->editingId ? 'Data pelanggan berhasil diperbarui.' : 'Pelanggan baru berhasil ditambahkan.');
        $this->resetForm();
        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $customer = Customer::find($id);
        if (! $customer) {
            return;
        }
        $this->deletingId = $id;
        $this->deletingName = $customer->name;
        $this->showDeleteModal = true;
    }

    /**
     * Soft delete pelanggan — data disembunyikan dari daftar utama tapi masih
     * ada di database. Admin bisa memulihkan lewat tampilan "Pelanggan Terhapus".
     * Relasi (damage_reports.customer_id, customer_photos) tetap utuh karena
     * baris pelanggan masih ada di database.
     */
    public function deleteCustomer(): void
    {
        Customer::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
        unset($this->customers, $this->trashedCount);
        $this->success('Pelanggan berhasil dihapus. Data dapat dipulihkan dari tampilan "Pelanggan Terhapus".');
    }

    /** Pulihkan pelanggan yang sudah di-soft-delete. */
    public function restoreCustomer(int $id): void
    {
        Customer::onlyTrashed()->findOrFail($id)->restore();
        unset($this->customers, $this->trashedCount);
        $this->success('Pelanggan berhasil dipulihkan.');
    }

    /** Konfirmasi hapus permanen (force delete) — hanya dari tampilan terhapus. */
    public function confirmForceDelete(int $id): void
    {
        $customer = Customer::onlyTrashed()->find($id);
        if (! $customer) {
            return;
        }
        $this->deletingId = $id;
        $this->deletingName = $customer->name;
        $this->showForceDeleteModal = true;
    }

    /**
     * Hapus permanen pelanggan — data benar-benar hilang dari database.
     * FK nullOnDelete di damage_reports akan mengeset customer_id → NULL
     * (snapshot customer_name/address tetap utuh).
     */
    public function forceDeleteCustomer(): void
    {
        Customer::onlyTrashed()->findOrFail($this->deletingId)->forceDelete();
        $this->showForceDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
        unset($this->customers, $this->trashedCount);
        $this->success('Pelanggan berhasil dihapus permanen.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->phone = '';
        $this->address = '';
        $this->ip_address = '';
        $this->internet_package_id = null;
        $this->status = CustomerStatus::Aktif->value;
        $this->latitude = '';
        $this->longitude = '';
        $this->installed_at = '';
        $this->resetValidation();
    }

    protected function tableComputed(): string|array
    {
        return 'customers';
    }
}; ?>

<div>
    <x-mary-header title="Pelanggan" separator class="mb-6!">
        <x-slot:actions>
            @unless($showTrashed)
                {{-- Ekspor mengikuti filter (search + status) yang sedang aktif --}}
                <x-dropdown label="Ekspor" icon="o-document-arrow-down" class="btn-ghost btn-sm rounded-full" right>
                    <li>
                        <a
                            href="{{ route('customers.export.pdf', ['search' => $search, 'status' => $filterStatus]) }}"
                            target="_blank"
                            class="flex items-center gap-2"
                        >
                            <x-mary-icon name="o-document-text" class="w-4 h-4" /> PDF
                        </a>
                    </li>
                    <li>
                        <a
                            href="{{ route('customers.export.excel', ['search' => $search, 'status' => $filterStatus]) }}"
                            class="flex items-center gap-2"
                        >
                            <x-mary-icon name="o-table-cells" class="w-4 h-4" /> Excel
                        </a>
                    </li>
                </x-dropdown>
                <x-mary-button
                    icon="o-plus"
                    label="Tambah Pelanggan"
                    class="btn-primary btn-sm rounded-full"
                    wire:click="openCreate"
                />
            @endunless
        </x-slot:actions>
    </x-mary-header>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari nama, HP, alamat, IP, atau paket..."
            icon="o-magnifying-glass"
            class="input-sm w-64 rounded-full"
        />
        @unless($showTrashed)
            <x-filter-chips :options="['' => 'Semua'] + \App\Enums\CustomerStatus::options()" field="filterStatus" :selected="$filterStatus" />
        @endunless

        {{-- Toggle tampilan terhapus --}}
        <button
            wire:click="toggleTrashed"
            class="btn btn-sm rounded-full {{ $showTrashed ? 'btn-warning' : 'btn-ghost' }} ml-auto"
        >
            <x-mary-icon name="{{ $showTrashed ? 'o-arrow-uturn-left' : 'o-archive-box' }}" class="w-4 h-4" />
            {{ $showTrashed ? 'Kembali ke Daftar Aktif' : 'Pelanggan Terhapus' }}
            @if(! $showTrashed && $this->trashedCount > 0)
                <span class="badge badge-warning badge-sm">{{ $this->trashedCount }}</span>
            @endif
        </button>
    </div>

    {{-- Banner tampilan terhapus --}}
    @if($showTrashed)
        <div class="alert alert-warning mb-4 rounded-xl">
            <x-mary-icon name="o-archive-box" class="w-5 h-5" />
            <div>
                <p class="font-semibold text-sm">Tampilan Pelanggan Terhapus</p>
                <p class="text-xs opacity-80">Pelanggan di daftar ini sudah dihapus. Anda dapat memulihkan atau menghapus permanen.</p>
            </div>
        </div>
    @endif

    {{-- Tabel --}}
    <x-table-card :rows="$this->customers" empty-icon="o-identification" :empty-text="$showTrashed ? 'Tidak ada pelanggan terhapus' : 'Tidak ada pelanggan ditemukan'">
        <x-slot:head>
            <th class="w-12">#</th>
            <th>Nama</th>
            <th>No. HP</th>
            <th>Alamat</th>
            <th>IP</th>
            <th>Paket</th>
            <th>Status</th>
            @if($showTrashed)
                <th>Dihapus</th>
            @endif
            <th class="w-20">Aksi</th>
        </x-slot:head>

        @foreach($this->customers as $customer)
            <tr class="hover:bg-base-200 transition-colors {{ $showTrashed ? 'opacity-70' : '' }}">
                <td class="text-base-content/40 text-xs">{{ $customer->id }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <x-avatar
                            :placeholder="strtoupper(substr($customer->name, 0, 1))"
                            class="w-7! h-7! text-xs! bg-secondary/10 text-secondary"
                        />
                        <span class="font-medium text-sm">{{ $customer->name }}</span>
                    </div>
                </td>
                <td class="text-sm text-base-content/70">
                    {{ $customer->phone !== '' ? $customer->phone : '—' }}
                </td>
                <td class="text-sm text-base-content/70 max-w-xs truncate" title="{{ $customer->address }}">
                    {{ $customer->address }}
                </td>
                <td class="text-xs text-base-content/60 font-mono">
                    {{ $customer->ip_address ?? '—' }}
                </td>
                <td class="text-xs text-base-content/60">
                    {{ $customer->internetPackage?->name ?? '—' }}
                </td>
                <td>
                    @php
                        // Pil status langganan (dot+pill). Kelas warna literal → terbaca scanner.
                        $statusStyle = match ($customer->status) {
                            \App\Enums\CustomerStatus::Aktif->value    => ['pill' => 'bg-success/15 text-success', 'dot' => 'bg-success'],
                            \App\Enums\CustomerStatus::Isolir->value   => ['pill' => 'bg-warning/15 text-warning', 'dot' => 'bg-warning'],
                            \App\Enums\CustomerStatus::Berhenti->value => ['pill' => 'bg-error/15 text-error',     'dot' => 'bg-error'],
                            default                                    => ['pill' => 'bg-base-200 text-base-content/60', 'dot' => 'bg-base-content/40'],
                        };
                        $statusLabel = \App\Enums\CustomerStatus::tryFrom($customer->status)?->label() ?? $customer->status;
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $statusStyle['pill'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusStyle['dot'] }}"></span>
                        {{ $statusLabel }}
                    </span>
                </td>
                @if($showTrashed)
                    <td class="text-xs text-base-content/50">
                        {{ $customer->deleted_at?->diffForHumans() }}
                    </td>
                @endif
                <td>
                    <div class="flex gap-1">
                        @if($showTrashed)
                            {{-- Tampilan terhapus: Pulihkan & Hapus Permanen --}}
                            <x-mary-button
                                icon="o-arrow-uturn-left"
                                class="btn-ghost btn-xs rounded-full text-success"
                                wire:click="restoreCustomer({{ $customer->id }})"
                                tooltip="Pulihkan"
                                spinner="restoreCustomer({{ $customer->id }})"
                            />
                            <x-mary-button
                                icon="o-trash"
                                class="btn-ghost btn-xs rounded-full text-error"
                                wire:click="confirmForceDelete({{ $customer->id }})"
                                tooltip="Hapus Permanen"
                            />
                        @else
                            {{-- Tampilan normal: Detail, Edit, Hapus (soft delete) --}}
                            <a
                                href="{{ route('customers.show', $customer) }}"
                                wire:navigate
                                class="btn btn-ghost btn-xs rounded-full"
                                title="Lihat Detail"
                            >
                                <x-mary-icon name="o-eye" class="w-4 h-4" />
                            </a>
                            <x-mary-button
                                icon="o-pencil"
                                class="btn-ghost btn-xs rounded-full"
                                wire:click="openEdit({{ $customer->id }})"
                                tooltip="Edit"
                            />
                            <x-mary-button
                                icon="o-trash"
                                class="btn-ghost btn-xs rounded-full text-error"
                                wire:click="confirmDelete({{ $customer->id }})"
                                tooltip="Hapus"
                            />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table-card>

    {{-- Modal Buat / Edit Pelanggan --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Pelanggan' : 'Tambah Pelanggan'" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-mary-input
                label="Nama Pelanggan"
                wire:model="name"
                placeholder="Pak Hendra"
                icon="o-user"
                required
            />
            <x-mary-input
                label="No. HP / WhatsApp"
                wire:model="phone"
                placeholder="0812xxxxxxxx"
                icon="o-phone"
                required
            />
        </div>

        <div class="mt-4">
            <x-mary-input
                label="Alamat Pemasangan"
                wire:model="address"
                placeholder="Jl. Mawar No. 5, Cibitung"
                icon="o-map-pin"
                required
            />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <x-mary-input
                label="IP Address"
                wire:model="ip_address"
                placeholder="192.168.x.x (opsional)"
                icon="o-globe-alt"
                hint="Pengganti kode pelanggan"
            />
            <x-select
                label="Paket Internet"
                wire:model="internet_package_id"
                :options="$this->packageOptions"
                option-value="id"
                option-label="name"
                placeholder="Pilih paket (opsional)"
                icon="o-wifi"
            />
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-base-content/70 mb-2">Status Langganan</label>
            {{-- Kartu pilihan status: has-checked me-highlight kartu via CSS murni --}}
            <div class="grid grid-cols-3 gap-3">
                @php
                    $statusCards = [
                        \App\Enums\CustomerStatus::Aktif->value    => ['icon' => 'o-check-circle', 'color' => 'text-success', 'desc' => 'Langganan jalan'],
                        \App\Enums\CustomerStatus::Isolir->value   => ['icon' => 'o-pause-circle', 'color' => 'text-warning', 'desc' => 'Ditangguhkan'],
                        \App\Enums\CustomerStatus::Berhenti->value => ['icon' => 'o-x-circle',     'color' => 'text-error',   'desc' => 'Tidak langganan'],
                    ];
                @endphp
                @foreach(\App\Enums\CustomerStatus::cases() as $case)
                    <label class="flex flex-col items-center text-center gap-1.5 cursor-pointer px-2 py-3 rounded-xl border border-base-300 hover:bg-base-200/60 transition-colors has-checked:border-primary has-checked:bg-primary/5">
                        <input type="radio" wire:model="status" value="{{ $case->value }}" class="sr-only" />
                        <x-mary-icon name="{{ $statusCards[$case->value]['icon'] }}" class="w-6 h-6 {{ $statusCards[$case->value]['color'] }}" />
                        <span class="text-sm font-semibold leading-tight">{{ $case->label() }}</span>
                        <span class="text-xs text-base-content/50 leading-tight">{{ $statusCards[$case->value]['desc'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <x-mary-input
                label="Latitude"
                wire:model="latitude"
                placeholder="-6.27 (opsional)"
                icon="o-map-pin"
            />
            <x-mary-input
                label="Longitude"
                wire:model="longitude"
                placeholder="107.10 (opsional)"
                icon="o-map-pin"
            />
            <x-mary-input
                label="Tanggal Pasang"
                wire:model="installed_at"
                type="date"
                icon="o-calendar"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Tambah Pelanggan'"
                class="btn-primary rounded-full"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus (soft delete) --}}
    <x-confirm-delete-modal title="Hapus Pelanggan" noun="pelanggan" :name="$deletingName" action="deleteCustomer" />

    {{-- Modal Konfirmasi Hapus Permanen (force delete) --}}
    <x-mary-modal wire:model="showForceDeleteModal" title="Hapus Permanen" separator>
        <div class="space-y-3">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-error/10 border border-error/20">
                <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6 text-error shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-error">Peringatan: Tindakan ini tidak dapat dibatalkan!</p>
                    <p class="text-xs text-base-content/70 mt-1">
                        Data pelanggan akan hilang <strong>permanen</strong> dari database dan tidak bisa dipulihkan lagi.
                        Laporan terkait tetap utuh, tetapi kolom pelanggan akan menjadi kosong (—).
                    </p>
                </div>
            </div>
            <p class="text-sm text-base-content/70">
                Yakin ingin menghapus permanen pelanggan
                <strong class="text-base-content">{{ $deletingName }}</strong>?
            </p>
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showForceDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus Permanen" class="btn-error rounded-full" wire:click="forceDeleteCustomer" spinner="forceDeleteCustomer" />
        </x-slot:actions>
    </x-mary-modal>
</div>
