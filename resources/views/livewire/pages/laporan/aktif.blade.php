<?php

use App\Actions\SyncReportTechnicians;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\WithTableFilters;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use WithPagination, Toast, WithTableFilters;

    // Filter & search
    public string $search = '';
    public string $filterStatus = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $deletingId = null;
    public ?string $deletingName = null;

    // Form fields
    public string $category = '';
    public ?int $customer_id = null;   // kategori pelanggan: pelanggan terpilih
    public string $title = '';         // kategori jaringan/pemeliharaan: judul laporan
    public string $address = '';       // alamat snapshot (pelanggan) / lokasi-area (non-pelanggan)
    public int|string|null $damage_type_id = '';
    public string $notes = '';
    public array $selectedTechnicians = [];

    public function mount(): void
    {
        $this->category = ReportCategory::Pelanggan->value;
    }

    #[Computed]
    public function reports()
    {
        return DamageReport::with(['damageType', 'taskAssignments.technician'])
            ->when($this->search, fn($q) =>
                $q->where(fn($w) =>
                    $w->where('customer_name', 'like', "%{$this->search}%")
                      ->orWhere('address', 'like', "%{$this->search}%")
                      ->orWhere('title', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterStatus, fn($q) =>
                $q->where('status', $this->filterStatus)
            )
            ->latest()
            ->paginate(10, ['*'], 'aktifPage');
    }

    #[Computed]
    public function damageTypes()
    {
        return DamageType::orderBy('name')->get();
    }

    #[Computed]
    public function technicians()
    {
        return User::where('role', UserRole::Teknisi->value)->orderBy('name')->get();
    }

    /** Pelanggan untuk search-select (kategori pelanggan). */
    #[Computed]
    public function customers()
    {
        return Customer::orderBy('name')->get()->map(fn ($c) => [
            'id'      => $c->id,
            'name'    => ($c->customer_code ? "[{$c->customer_code}] " : '') . $c->name,
            'address' => $c->address,
        ]);
    }

    /** Apakah kategori terpilih saat ini membutuhkan pelanggan terdaftar? */
    public function butuhPelanggan(): bool
    {
        return ReportCategory::tryFrom($this->category)?->butuhPelanggan() ?? false;
    }

    /** Apakah kategori terpilih saat ini mewajibkan jenis gangguan? */
    public function butuhJenisGangguan(): bool
    {
        return ReportCategory::tryFrom($this->category)?->butuhJenisGangguan() ?? false;
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
        $this->category = $report->category;
        $this->customer_id = $report->customer_id;
        $this->title = $report->title ?? '';
        $this->address = $report->address;
        $this->damage_type_id = $report->damage_type_id;
        $this->notes = $report->notes ?? '';
        $this->selectedTechnicians = $report->taskAssignments->pluck('technician_id')->toArray();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        // Validasi bersyarat per kategori: pelanggan butuh customer_id (snapshot
        // nama/alamat diambil dari pelanggan terpilih); jaringan/pemeliharaan butuh
        // judul + lokasi (tanpa pelanggan). Sumber kebenaran = ReportCategory.
        // Jenis gangguan hanya wajib untuk gangguan pelanggan riil. Normalkan '' (sentinel
        // "belum dipilih" dari <x-select>) menjadi null agar aturan `nullable` benar-benar
        // lewat saat opsional — string '' bukan null, jadi `exists` akan jalan & gagal.
        if ($this->damage_type_id === '') {
            $this->damage_type_id = null;
        }

        $rules = [
            'category'       => 'required|in:' . implode(',', ReportCategory::values()),
            'damage_type_id' => ($this->butuhJenisGangguan() ? 'required' : 'nullable') . '|exists:damage_types,id',
            'notes'          => 'nullable|string',
            // Tiap ID harus user dengan role teknisi — tolak ID palsu/manipulasi
            // (cegah error 500 dari FK) & cegah admin diselundupkan jadi teknisi.
            'selectedTechnicians'   => 'array',
            'selectedTechnicians.*' => 'integer|exists:users,id,role,' . UserRole::Teknisi->value,
        ];

        if ($this->butuhPelanggan()) {
            $rules['customer_id'] = 'required|exists:customers,id';
        } else {
            $rules['title']   = 'required|string|max:255';
            $rules['address'] = 'required|string|max:255';
        }

        $this->validate($rules);

        // Susun atribut sesuai kategori. Untuk pelanggan: snapshot nama/alamat dari
        // pelanggan terpilih (riwayat & PDF tak ikut berubah bila data pelanggan
        // kelak diperbarui). Untuk non-pelanggan: customer_id/customer_name NULL.
        if ($this->butuhPelanggan()) {
            $customer = Customer::findOrFail($this->customer_id);
            $attributes = [
                'category'      => $this->category,
                'customer_id'   => $customer->id,
                'customer_name' => $customer->name,
                'customer_ip'   => $customer->ip_address,
                'title'         => null,
                'address'       => $customer->address,
            ];
        } else {
            $attributes = [
                'category'      => $this->category,
                'customer_id'   => null,
                'customer_name' => null,
                'customer_ip'   => null,
                'title'         => $this->title,
                'address'       => $this->address,
            ];
        }

        $attributes['damage_type_id'] = $this->damage_type_id;
        $attributes['notes'] = $this->notes ?: null;

        // Simpan laporan + sinkron penugasan dalam satu transaksi: bila sync gagal
        // di tengah jalan, laporan tidak tertinggal dalam keadaan setengah jadi.
        DB::transaction(function () use ($attributes) {
            if ($this->editingId) {
                $report = DamageReport::findOrFail($this->editingId);
                $report->update($attributes);
            } else {
                $report = DamageReport::create($attributes + [
                    'created_by' => auth()->id(),
                    'status'     => ReportStatus::Ditugaskan->value,
                ]);
            }

            // Sinkronkan penugasan teknisi + notifikasi teknisi baru (sumber tunggal)
            (new SyncReportTechnicians)($report, $this->selectedTechnicians);
        });

        $this->showFormModal = false;
        unset($this->reports);
        $this->success($this->editingId ? 'Laporan berhasil diperbarui.' : 'Laporan baru berhasil dibuat.');
        $this->resetForm();
        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $report = DamageReport::find($id);
        if (! $report) {
            return;
        }
        $this->deletingId = $id;
        $this->deletingName = $report->judul;
        $this->showDeleteModal = true;
    }

    public function deleteReport(): void
    {
        DamageReport::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
        unset($this->reports);
        $this->success('Laporan berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->category = ReportCategory::Pelanggan->value;
        $this->customer_id = null;
        $this->title = '';
        $this->address = '';
        $this->damage_type_id = '';
        $this->notes = '';
        $this->selectedTechnicians = [];
        $this->resetValidation();
    }

    protected function tableComputed(): string|array
    {
        return 'reports';
    }
}; ?>

<div>
    {{-- Action, Filter & Search --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-mary-input
                wire:model.live.debounce="search"
                placeholder="Cari pelanggan atau alamat..."
                icon="o-magnifying-glass"
                class="input-sm w-56 rounded-full"
            />
            <x-filter-chips :options="$this->statusOptions" field="filterStatus" :selected="$filterStatus" />
        </div>

        <x-mary-button
            icon="o-plus"
            label="Buat Laporan"
            class="btn-primary btn-sm rounded-full"
            wire:click="openCreate"
        />
    </div>

    {{-- Tabel --}}
    <x-table-card :rows="$this->reports" empty-icon="o-document-text" empty-text="Tidak ada laporan ditemukan">
        <x-slot:head>
            <th class="w-12">#</th>
            <th>Laporan</th>
            <th>Alamat</th>
            <th>Jenis Gangguan</th>
            <th>Teknisi</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th class="w-20">Aksi</th>
        </x-slot:head>

        @foreach($this->reports as $report)
            <tr class="hover:bg-base-200 transition-colors">
                <td class="text-base-content/40 text-xs">{{ $report->id }}</td>
                <td class="font-medium">
                    <div>{{ $report->judul }}</div>
                    <div class="mt-1">
                        <x-category-pill :category="$report->category" />
                    </div>
                </td>
                <td class="text-sm text-base-content/70 max-w-40 truncate">{{ $report->address }}</td>
                <td class="text-sm">{{ $report->damageType?->name ?? '—' }}</td>
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
    </x-table-card>

    {{-- Modal Buat / Edit Laporan --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Laporan' : 'Buat Laporan Baru'" separator>
        {{-- Pemilih kategori: men-toggle isi form (pelanggan vs jaringan/pemeliharaan) --}}
        <div>
            <label class="block text-sm font-medium text-base-content/70 mb-2">Kategori Laporan</label>
            @php
                $categoryCards = [
                    \App\Enums\ReportCategory::Pelanggan->value    => ['icon' => 'o-user',               'color' => 'text-primary',   'title' => 'Pelanggan',    'desc' => 'Gangguan 1 pelanggan'],
                    \App\Enums\ReportCategory::Jaringan->value     => ['icon' => 'o-signal',             'color' => 'text-info',      'title' => 'Jaringan',     'desc' => 'Infrastruktur, banyak'],
                    \App\Enums\ReportCategory::Pemeliharaan->value => ['icon' => 'o-wrench-screwdriver', 'color' => 'text-secondary', 'title' => 'Pemeliharaan', 'desc' => 'Perawatan rutin'],
                ];
            @endphp
            <div class="grid grid-cols-3 gap-3">
                @foreach(\App\Enums\ReportCategory::cases() as $cat)
                    <label class="flex flex-col items-center text-center gap-1.5 cursor-pointer px-2 py-3 rounded-xl border border-base-300 hover:bg-base-200/60 transition-colors has-checked:border-primary has-checked:bg-primary/5">
                        <input type="radio" wire:model.live="category" value="{{ $cat->value }}" class="sr-only" />
                        <x-mary-icon name="{{ $categoryCards[$cat->value]['icon'] }}" class="w-6 h-6 {{ $categoryCards[$cat->value]['color'] }}" />
                        <span class="text-sm font-semibold leading-tight">{{ $categoryCards[$cat->value]['title'] }}</span>
                        <span class="text-xs text-base-content/50 leading-tight">{{ $categoryCards[$cat->value]['desc'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Field bersyarat per kategori --}}
        @if($this->butuhPelanggan())
            <div class="mt-4">
                <x-choices-offline
                    label="Pelanggan"
                    wire:model="customer_id"
                    :options="$this->customers"
                    option-label="name"
                    option-sub-label="address"
                    single
                    searchable
                    clearable
                    icon="o-user"
                    placeholder="Cari & pilih pelanggan terdaftar..."
                    no-result-text="Pelanggan tidak ditemukan"
                    hint="Nama & alamat disimpan sebagai snapshot saat laporan dibuat"
                />
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <x-mary-input
                    label="Judul Laporan"
                    wire:model="title"
                    placeholder="mis. Kabel utama putus area Cibitung"
                    icon="o-bolt"
                    required
                />
                <x-mary-input
                    label="Lokasi / Area Terdampak"
                    wire:model="address"
                    placeholder="mis. Backbone RT 03, Cibitung"
                    icon="o-map-pin"
                    required
                />
            </div>
        @endif

        <div class="mt-4">
            <x-select
                label="Jenis Gangguan/Pekerjaan"
                wire:model="damage_type_id"
                :options="$this->damageTypes"
                option-value="id"
                option-label="name"
                :placeholder="$this->butuhJenisGangguan() ? 'Pilih jenis gangguan...' : 'Pilih jenis (opsional)...'"
                icon="o-wrench-screwdriver"
                :required="$this->butuhJenisGangguan()"
                :hint="$this->butuhJenisGangguan() ? null : 'Opsional untuk jaringan/pemeliharaan'"
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
    <x-confirm-delete-modal title="Hapus Laporan" noun="laporan" :name="$deletingName" action="deleteReport" />
</div>
