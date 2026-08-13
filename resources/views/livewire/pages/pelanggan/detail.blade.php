<?php

use App\Enums\CustomerStatus;
use App\Enums\ReportStatus;
use App\Models\Customer;
use App\Models\CustomerPhoto;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, Toast;

    public int $customerId;

    // Upload foto rumah
    public array $newPhotos = [];
    public string $caption = '';

    // Konfirmasi hapus foto (modal in-app, bukan confirm() browser)
    public bool $showPhotoDeleteModal = false;
    public ?int $deletingPhotoId = null;

    public function mount(Customer $customer): void
    {
        $this->customerId = $customer->id;
    }

    #[Computed]
    public function customer()
    {
        return Customer::with([
            'photos' => fn ($q) => $q->latest('created_at'),
            'reports' => fn ($q) => $q->with(['damageType', 'taskAssignments.technician'])->latest(),
        ])->findOrFail($this->customerId);
    }

    /**
     * Ringkasan pola komplain — dihitung dari koleksi reports yang sudah dimuat
     * (tanpa query tambahan). Bahan analisis untuk bab pembahasan skripsi.
     */
    #[Computed]
    public function stats(): array
    {
        $reports = $this->customer->reports;

        $tersering = $reports->groupBy('damage_type_id')
            ->sortByDesc(fn ($group) => $group->count())
            ->first();

        return [
            'total'      => $reports->count(),
            'bulan_ini'  => $reports->filter(fn ($r) => $r->created_at?->isCurrentMonth())->count(),
            'tersering'  => $tersering?->first()?->damageType?->name,
            'terakhir'   => $reports->first()?->created_at,
        ];
    }

    public function uploadPhotos(): void
    {
        $this->validate([
            'newPhotos'   => 'required|array|max:10',
            'newPhotos.*' => 'image|max:2048', // maks 2 MB per foto
            'caption'     => 'nullable|string|max:255',
        ], attributes: ['newPhotos.*' => 'foto']);

        foreach ($this->newPhotos as $file) {
            CustomerPhoto::create([
                'customer_id' => $this->customerId,
                'uploaded_by' => auth()->id(),
                'path'        => $file->store('customer-photos', 'public'),
                'caption'     => $this->caption !== '' ? $this->caption : null,
            ]);
        }

        $this->reset('newPhotos', 'caption');
        unset($this->customer);
        $this->success('Foto rumah berhasil diunggah.');
    }

    public function confirmDeletePhoto(int $photoId): void
    {
        $this->deletingPhotoId = $photoId;
        $this->showPhotoDeleteModal = true;
    }

    public function deletePhoto(): void
    {
        $photo = CustomerPhoto::where('customer_id', $this->customerId)->find($this->deletingPhotoId);

        if ($photo) {
            Storage::disk('public')->delete($photo->path);
            $photo->delete();
            unset($this->customer);
            $this->success('Foto dihapus.');
        }

        $this->showPhotoDeleteModal = false;
        $this->deletingPhotoId = null;
    }
}; ?>

<div>
    @php $c = $this->customer; @endphp

    <x-mary-header :title="$c->name" subtitle="Detail Pelanggan" separator class="mb-6!">
        <x-slot:actions>
            <a href="{{ route('customers.index') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full">
                <x-mary-icon name="o-arrow-left" class="w-4 h-4" /> Kembali
            </a>
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Kolom kiri: identitas + ringkasan + galeri --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Kartu identitas --}}
            <x-mary-card class="rounded-2xl">
                @php
                    $statusStyle = match ($c->status) {
                        CustomerStatus::Aktif->value    => ['pill' => 'bg-success/15 text-success', 'dot' => 'bg-success'],
                        CustomerStatus::Isolir->value   => ['pill' => 'bg-warning/15 text-warning', 'dot' => 'bg-warning'],
                        CustomerStatus::Berhenti->value => ['pill' => 'bg-error/15 text-error',     'dot' => 'bg-error'],
                        default                         => ['pill' => 'bg-base-200 text-base-content/60', 'dot' => 'bg-base-content/40'],
                    };
                    $statusLabel = CustomerStatus::tryFrom($c->status)?->label() ?? $c->status;
                @endphp
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-avatar :placeholder="strtoupper(substr($c->name, 0, 1))" class="w-12! h-12! bg-secondary/10 text-secondary" />
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold truncate">{{ $c->name }}</h3>
                            <p class="text-sm text-base-content/50">{{ $c->internetPackage?->name ?? 'Paket belum diisi' }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $statusStyle['pill'] }} shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusStyle['dot'] }}"></span>
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div class="flex gap-3">
                        <x-mary-icon name="o-phone" class="w-4 h-4 text-base-content/40 shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-xs text-base-content/40">No. HP / WhatsApp</p>
                            <p>{{ $c->phone !== '' ? $c->phone : '—' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <x-mary-icon name="o-globe-alt" class="w-4 h-4 text-base-content/40 shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-xs text-base-content/40">IP Address</p>
                            <p class="font-mono">{{ $c->ip_address ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3 sm:col-span-2">
                        <x-mary-icon name="o-map-pin" class="w-4 h-4 text-base-content/40 shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-xs text-base-content/40">Alamat</p>
                            <p>{{ $c->address }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <x-mary-icon name="o-calendar" class="w-4 h-4 text-base-content/40 shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-xs text-base-content/40">Tanggal Pasang</p>
                            <p>{{ $c->installed_at?->translatedFormat('d F Y') ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <x-mary-icon name="o-map" class="w-4 h-4 text-base-content/40 shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-xs text-base-content/40">Koordinat</p>
                            <p>{{ ($c->latitude !== null && $c->longitude !== null) ? "{$c->latitude}, {$c->longitude}" : '—' }}</p>
                        </div>
                    </div>
                </div>
            </x-mary-card>

            {{-- Galeri foto rumah --}}
            <x-mary-card class="rounded-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-photo" class="w-5 h-5 text-base-content/50" />
                        <h3 class="font-semibold">Foto Rumah</h3>
                        <span class="text-xs text-base-content/40">({{ $c->photos->count() }})</span>
                    </div>
                </div>

                <p class="text-xs text-base-content/50 mb-4">
                    Alat bantu menemukan lokasi (alamat perkampungan sering tidak presisi). Tampak depan rumah, patokan gang, warna pagar, dsb.
                </p>

                {{-- Form unggah --}}
                <div class="rounded-xl border border-dashed border-base-300 p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-base-content/60 mb-1.5">Pilih foto (bisa beberapa)</label>
                            <input
                                type="file"
                                wire:model="newPhotos"
                                multiple
                                accept="image/*"
                                class="file-input file-input-bordered file-input-sm w-full rounded-xl"
                            />
                            @error('newPhotos.*') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            @error('newPhotos') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-base-content/60 mb-1.5">Keterangan (opsional)</label>
                            <x-mary-input wire:model="caption" placeholder="mis. patokan gang" icon="o-tag" class="input-sm rounded-xl" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-3">
                        <x-mary-button
                            label="Unggah"
                            icon="o-arrow-up-tray"
                            class="btn-primary btn-sm rounded-full"
                            wire:click="uploadPhotos"
                            spinner="uploadPhotos"
                        />
                        <span wire:loading wire:target="newPhotos" class="text-xs text-base-content/50">Memuat foto…</span>
                    </div>
                </div>

                {{-- Grid thumbnail --}}
                @if($c->photos->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($c->photos as $photo)
                            <div class="group relative rounded-xl overflow-hidden border border-base-300 aspect-square">
                                <a href="{{ asset('storage/' . $photo->path) }}" target="_blank">
                                    <img
                                        src="{{ asset('storage/' . $photo->path) }}"
                                        alt="{{ $photo->caption ?? 'Foto rumah' }}"
                                        class="w-full h-full object-cover"
                                    />
                                </a>
                                <button
                                    wire:click="confirmDeletePhoto({{ $photo->id }})"
                                    class="absolute top-1.5 right-1.5 w-7 h-7 flex items-center justify-center rounded-full bg-base-100/80 text-error opacity-0 group-hover:opacity-100 transition-opacity hover:bg-error hover:text-error-content"
                                    title="Hapus foto"
                                >
                                    <x-mary-icon name="o-trash" class="w-3.5 h-3.5" />
                                </button>
                                @if($photo->caption)
                                    <div class="absolute bottom-0 inset-x-0 bg-linear-to-t from-black/70 to-transparent px-2 py-1.5">
                                        <p class="text-xs text-white truncate">{{ $photo->caption }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-base-content/40">
                        <x-mary-icon name="o-photo" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                        <p class="text-sm">Belum ada foto rumah</p>
                    </div>
                @endif
            </x-mary-card>
        </div>

        {{-- Kolom kanan: ringkasan komplain --}}
        <div class="space-y-4">
            <x-mary-card class="rounded-2xl">
                <h3 class="font-semibold mb-4 flex items-center gap-2">
                    <x-mary-icon name="o-chart-bar" class="w-5 h-5 text-base-content/50" />
                    Ringkasan Komplain
                </h3>

                @php $s = $this->stats; @endphp
                <div class="space-y-3">
                    <div class="flex items-center justify-between rounded-xl bg-base-200/60 p-3">
                        <span class="text-sm text-base-content/60">Total Komplain</span>
                        <span class="text-lg font-bold">{{ $s['total'] }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-base-200/60 p-3">
                        <span class="text-sm text-base-content/60">Bulan Ini</span>
                        <span class="text-lg font-bold">{{ $s['bulan_ini'] }}</span>
                    </div>
                    <div class="rounded-xl bg-base-200/60 p-3">
                        <p class="text-sm text-base-content/60 mb-0.5">Kerusakan Tersering</p>
                        <p class="text-sm font-semibold">{{ $s['tersering'] ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-base-200/60 p-3">
                        <p class="text-sm text-base-content/60 mb-0.5">Terakhir Komplain</p>
                        <p class="text-sm font-semibold">{{ $s['terakhir']?->translatedFormat('d F Y') ?? '—' }}</p>
                    </div>
                </div>
            </x-mary-card>
        </div>
    </div>

    {{-- Riwayat perbaikan pelanggan (semua status) --}}
    <div class="mt-6">
        <x-mary-card class="rounded-2xl">
            <h3 class="font-semibold mb-4 flex items-center gap-2">
                <x-mary-icon name="o-wrench-screwdriver" class="w-5 h-5 text-base-content/50" />
                Riwayat Perbaikan
            </h3>

            @if($c->reports->isNotEmpty())
                <div class="overflow-x-auto no-scrollbar">
                    <table class="table table-sm w-full">
                        <thead>
                            <tr class="text-xs text-base-content/50 uppercase">
                                <th>Tanggal</th>
                                <th>Jenis Gangguan</th>
                                <th>Status</th>
                                <th>Teknisi</th>
                                <th>Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($c->reports as $report)
                                <tr class="hover:bg-base-200 transition-colors">
                                    <td class="text-sm text-base-content/70">{{ $report->created_at->format('d/m/Y') }}</td>
                                    <td class="text-sm">{{ $report->damageType?->name ?? '—' }}</td>
                                    <td><x-status-pill :status="$report->status" /></td>
                                    <td class="text-sm">
                                        {{ $report->taskAssignments->pluck('technician.name')->filter()->join(', ') ?: '—' }}
                                    </td>
                                    <td class="text-xs text-base-content/50">
                                        @if($report->status === ReportStatus::Selesai->value)
                                            {{ $report->durasiPenanganan(singkat: true) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-base-content/40">
                    <x-mary-icon name="o-wrench-screwdriver" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">Belum ada laporan untuk pelanggan ini</p>
                </div>
            @endif
        </x-mary-card>
    </div>

    {{-- Modal konfirmasi hapus foto (in-app, bukan confirm() browser) --}}
    <x-mary-modal wire:model="showPhotoDeleteModal" title="Hapus Foto" separator>
        <p class="text-sm text-base-content/70">
            Yakin ingin menghapus foto rumah ini? Tindakan ini tidak dapat dibatalkan.
        </p>
        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showPhotoDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus" class="btn-error rounded-full" wire:click="deletePhoto" spinner="deletePhoto" />
        </x-slot:actions>
    </x-mary-modal>
</div>
