<?php

use App\Models\DamageReport;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterPeriod = '';

    public bool $showDetailModal = false;
    public ?int $detailId = null;

    #[Computed]
    public function riwayat()
    {
        return DamageReport::with(['damageType', 'taskAssignments.technician'])
            ->where('status', 'selesai')
            ->when($this->search, fn($q) =>
                $q->where('customer_name', 'like', "%{$this->search}%")
                  ->orWhere('address', 'like', "%{$this->search}%")
            )
            ->when($this->filterPeriod === 'minggu', fn($q) =>
                $q->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])
            )
            ->when($this->filterPeriod === 'bulan', fn($q) =>
                $q->whereMonth('updated_at', now()->month)
                  ->whereYear('updated_at', now()->year)
            )
            ->latest('updated_at')
            ->paginate(10);
    }

    #[Computed]
    public function detailReport()
    {
        if (! $this->detailId) return null;

        return DamageReport::with([
            'damageType',
            'taskAssignments.technician',
            'workLogs.technician',
        ])->find($this->detailId);
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        unset($this->detailReport);
        $this->showDetailModal = true;
    }

    public function updatedSearch(): void { $this->resetPage(); unset($this->riwayat); }
    public function updatedFilterPeriod(): void { $this->resetPage(); unset($this->riwayat); }
}; ?>

<div>
    <x-mary-header title="Riwayat Pekerjaan" separator class="mb-6!" />

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari pelanggan atau alamat..."
            icon="o-magnifying-glass"
            class="input-sm w-56 rounded-full"
        />
        <div class="flex items-center gap-2 flex-wrap">
            @foreach (['' => 'Semua', 'minggu' => 'Minggu Ini', 'bulan' => 'Bulan Ini'] as $val => $label)
                <button
                    wire:click="$set('filterPeriod', '{{ $val }}')"
                    @class([
                        'btn btn-sm rounded-full',
                        'btn-primary' => $filterPeriod === $val,
                        'btn-ghost border border-base-300' => $filterPeriod !== $val,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
        <span class="text-xs text-base-content/40 self-center ml-auto">
            {{ $this->riwayat->total() }} laporan selesai
        </span>
    </div>

    {{-- Tabel --}}
    <x-mary-card class="rounded-2xl">
        @if($this->riwayat->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-mary-icon name="o-clock" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="text-sm">Belum ada laporan selesai</p>
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
                            <th>Selesai</th>
                            <th>Durasi</th>
                            <th class="w-16">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->riwayat as $report)
                            <tr class="hover:bg-base-200 transition-colors">
                                <td class="text-base-content/40 text-xs">{{ $report->id }}</td>
                                <td class="font-medium text-sm">{{ $report->customer_name }}</td>
                                <td class="text-sm text-base-content/70 max-w-[140px] truncate">{{ $report->address }}</td>
                                <td class="text-sm">{{ $report->damageType->name }}</td>
                                <td class="text-sm">
                                    @if($report->taskAssignments->isNotEmpty())
                                        {{ $report->taskAssignments->pluck('technician.name')->join(', ') }}
                                    @else
                                        <span class="text-base-content/30 italic">—</span>
                                    @endif
                                </td>
                                <td class="text-xs text-base-content/60">
                                    {{ $report->updated_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="text-xs text-base-content/50">
                                    @php
                                        $durasi = $report->created_at->diff($report->updated_at);
                                        if ($durasi->days > 0)
                                            echo $durasi->days . ' hari';
                                        elseif ($durasi->h > 0)
                                            echo $durasi->h . ' jam';
                                        else
                                            echo $durasi->i . ' mnt';
                                    @endphp
                                </td>
                                <td>
                                    <x-mary-button
                                        icon="o-eye"
                                        class="btn-ghost btn-xs"
                                        wire:click="openDetail({{ $report->id }})"
                                        tooltip="Lihat Detail"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mary-pagination :rows="$this->riwayat" class="mt-4" />
        @endif
    </x-mary-card>

    {{-- Modal Detail --}}
    <x-mary-modal wire:model="showDetailModal" title="Detail Laporan" separator box-class="max-w-lg">
        @if($this->detailReport)
            @php $report = $this->detailReport; @endphp

            {{-- Info laporan --}}
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm mb-4">
                <div>
                    <p class="text-xs text-base-content/50 uppercase mb-0.5">Pelanggan</p>
                    <p class="font-medium">{{ $report->customer_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50 uppercase mb-0.5">Jenis Gangguan</p>
                    <p>{{ $report->damageType->name }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-base-content/50 uppercase mb-0.5">Alamat</p>
                    <p>{{ $report->address }}</p>
                </div>
                @if($report->notes)
                    <div class="col-span-2">
                        <p class="text-xs text-base-content/50 uppercase mb-0.5">Keterangan</p>
                        <p class="text-base-content/70">{{ $report->notes }}</p>
                    </div>
                @endif
                <div class="col-span-2">
                    <p class="text-xs text-base-content/50 uppercase mb-0.5">Teknisi</p>
                    <p>{{ $report->taskAssignments->pluck('technician.name')->join(', ') ?: '—' }}</p>
                </div>
            </div>

            {{-- Timeline work_logs --}}
            @if($report->workLogs->isNotEmpty())
                <div class="border-t border-base-200 pt-4">
                    <p class="text-xs text-base-content/50 uppercase mb-3">Log Aktivitas</p>
                    <ol class="relative border-s border-base-300 ms-2 space-y-4">
                        @foreach($report->workLogs->sortBy('logged_at') as $log)
                            @php
                                $dot = match($log->status) {
                                    'ditugaskan'         => 'bg-warning',
                                    'sedang_memperbaiki' => 'bg-info',
                                    'selesai'            => 'bg-success',
                                    default              => 'bg-base-300',
                                };
                                $statusLabel = match($log->status) {
                                    'ditugaskan'         => 'Ditugaskan',
                                    'sedang_memperbaiki' => 'Mulai Memperbaiki',
                                    'selesai'            => 'Selesai',
                                    default              => $log->status,
                                };
                            @endphp
                            <li class="ms-4">
                                <div class="absolute -start-1.5 w-3 h-3 rounded-full border-2 border-base-100 {{ $dot }}"></div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="text-sm font-medium">{{ $statusLabel }}</p>
                                    <p class="text-xs text-base-content/40 shrink-0">{{ $log->logged_at->format('d/m H:i') }}</p>
                                </div>
                                @if($log->technician)
                                    <p class="text-xs text-base-content/50">{{ $log->technician->name }}</p>
                                @endif
                                @if($log->description)
                                    <p class="text-xs text-base-content/60 mt-0.5">{{ $log->description }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="border-t border-base-200 pt-4">
                    <p class="text-xs text-base-content/40 text-center py-4">Belum ada log aktivitas</p>
                </div>
            @endif
        @endif

        <x-slot:actions>
            <x-mary-button label="Tutup" class="btn-ghost" wire:click="$set('showDetailModal', false)" />
        </x-slot:actions>
    </x-mary-modal>
</div>
