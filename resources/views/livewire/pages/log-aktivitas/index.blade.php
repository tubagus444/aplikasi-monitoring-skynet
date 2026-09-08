<?php

use App\Livewire\Concerns\WithTableFilters;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, WithTableFilters;

    // Filter & search
    public string $search = '';
    public string $filterLogName = '';
    public string $filterEvent = '';
    public string $filterPeriod = '';
    public string $filterStartDate = '';
    public string $filterEndDate = '';

    // Modal state
    public bool $showDetailModal = false;
    public ?int $selectedActivityId = null;

    #[Computed]
    public function selectedActivity(): ?Activity
    {
        if (! $this->selectedActivityId) {
            return null;
        }

        return Activity::with(['causer', 'subject'])->find($this->selectedActivityId);
    }

    #[Computed]
    public function activities()
    {
        return Activity::with(['causer', 'subject'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', "%{$this->search}%")
                        ->orWhere('log_name', 'like', "%{$this->search}%")
                        ->orWhere('properties', 'like', "%{$this->search}%")
                        ->orWhereHasMorph('causer', ['*'], function ($causerQuery) {
                            $causerQuery->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->filterLogName, fn ($q) => $q->where('log_name', $this->filterLogName))
            ->when($this->filterEvent, fn ($q) => $q->where('event', $this->filterEvent))
            ->when($this->filterPeriod === 'today', fn ($q) => $q->whereDate('created_at', today()))
            ->when($this->filterPeriod === 'yesterday', fn ($q) => $q->whereDate('created_at', today()->subDay()))
            ->when($this->filterPeriod === '7days', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->when($this->filterPeriod === '30days', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
            ->when($this->filterPeriod === 'month', fn ($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
            ->when($this->filterPeriod === 'last_month', fn ($q) => $q->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year))
            ->when($this->filterPeriod === '3months', fn ($q) => $q->where('created_at', '>=', now()->subMonths(3)))
            ->when($this->filterPeriod === 'custom', function ($q) {
                $q->when($this->filterStartDate, fn ($sq) => $sq->whereDate('created_at', '>=', $this->filterStartDate))
                  ->when($this->filterEndDate, fn ($sq) => $sq->whereDate('created_at', '<=', $this->filterEndDate));
            })
            ->latest('id')
            ->paginate(15);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterLogName', 'filterEvent', 'filterPeriod', 'filterStartDate', 'filterEndDate']);
        $this->resetPage();
        unset($this->activities);
    }

    public function showDetail(int $id): void
    {
        $this->selectedActivityId = $id;
        unset($this->selectedActivity);
        $this->showDetailModal = true;
    }

    protected function tableComputed(): string|array
    {
        return 'activities';
    }
}; ?>

<div>
    {{-- Header --}}
    <x-mary-header title="Audit Trail / Log Aktivitas" subtitle="Rekam jejak seluruh tindakan dan perubahan data oleh pengguna web admin" separator class="mb-6!">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-base-200 text-base-content/70 border border-base-300">
                    <x-mary-icon name="o-shield-check" class="w-4 h-4 text-primary" />
                    Audit Trail Aktif
                </span>
            </div>
        </x-slot:actions>
    </x-mary-header>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-5">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari user, aktivitas, keterangan..."
            icon="o-magnifying-glass"
            class="input-sm w-full sm:w-64 rounded-full"
            clearable
        />

        {{-- Dropdown Modul --}}
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            <button
                type="button"
                @click="open = !open"
                @class([
                    'btn btn-ghost btn-sm rounded-full gap-2 text-xs font-normal border transition-all duration-200 [background-image:none] shadow-none',
                    '!bg-primary/15 !text-primary !border-primary/40 font-semibold' => !empty($filterLogName),
                    'border-base-300 bg-base-100/60 hover:bg-base-200 text-base-content/80' => empty($filterLogName),
                ])
            >
                <x-mary-icon name="o-squares-2x2" class="w-3.5 h-3.5 opacity-70" />
                <span>{{ $filterLogName ? ucfirst(str_replace('-', ' ', $filterLogName)) : 'Semua Modul' }}</span>
                <x-mary-icon name="o-chevron-down" class="w-3 h-3 opacity-50 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
            </button>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                class="absolute left-0 top-full mt-2 z-30 min-w-52 p-1.5 bg-base-100/95 backdrop-blur-md rounded-2xl shadow-xl border border-base-300/80 space-y-0.5"
                style="display: none;"
            >
                @php
                    $modulOptions = [
                        '' => ['label' => 'Semua Modul', 'dot' => 'bg-base-content/30'],
                        'laporan' => ['label' => 'Laporan', 'dot' => 'bg-primary'],
                        'pelanggan' => ['label' => 'Pelanggan', 'dot' => 'bg-secondary'],
                        'pengguna' => ['label' => 'Pengguna', 'dot' => 'bg-accent'],
                        'paket-internet' => ['label' => 'Paket Internet', 'dot' => 'bg-warning'],
                        'ip-pool' => ['label' => 'IP Pool', 'dot' => 'bg-purple-500'],
                    ];
                @endphp

                @foreach($modulOptions as $val => $opt)
                    <button
                        type="button"
                        wire:click="$set('filterLogName', '{{ $val }}')"
                        @click="open = false"
                        @class([
                            'w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors',
                            'bg-primary/10 text-primary font-semibold' => $filterLogName === (string)$val,
                            'hover:bg-base-200 text-base-content/80' => $filterLogName !== (string)$val,
                        ])
                    >
                        <span class="inline-flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $opt['dot'] }}"></span>
                            {{ $opt['label'] }}
                        </span>
                        @if($filterLogName === (string)$val)
                            <x-mary-icon name="o-check" class="w-3.5 h-3.5 text-primary" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Dropdown Tindakan --}}
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            <button
                type="button"
                @click="open = !open"
                @class([
                    'btn btn-ghost btn-sm rounded-full gap-2 text-xs font-normal border transition-all duration-200 [background-image:none] shadow-none',
                    '!bg-info/15 !text-info !border-info/40 font-semibold' => !empty($filterEvent),
                    'border-base-300 bg-base-100/60 hover:bg-base-200 text-base-content/80' => empty($filterEvent),
                ])
            >
                <x-mary-icon name="o-bolt" class="w-3.5 h-3.5 opacity-70" />
                <span>{{ $filterEvent ? ucfirst($filterEvent) : 'Semua Tindakan' }}</span>
                <x-mary-icon name="o-chevron-down" class="w-3 h-3 opacity-50 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
            </button>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                class="absolute left-0 top-full mt-2 z-30 min-w-56 p-1.5 bg-base-100/95 backdrop-blur-md rounded-2xl shadow-xl border border-base-300/80 space-y-0.5"
                style="display: none;"
            >
                @php
                    $eventOptions = [
                        '' => ['label' => 'Semua Tindakan', 'dot' => 'bg-base-content/30'],
                        'created' => ['label' => 'Created (Penambahan)', 'dot' => 'bg-success'],
                        'updated' => ['label' => 'Updated (Perubahan)', 'dot' => 'bg-info'],
                        'deleted' => ['label' => 'Deleted (Penghapusan)', 'dot' => 'bg-error'],
                    ];
                @endphp

                @foreach($eventOptions as $val => $opt)
                    <button
                        type="button"
                        wire:click="$set('filterEvent', '{{ $val }}')"
                        @click="open = false"
                        @class([
                            'w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors',
                            'bg-info/10 text-info font-semibold' => $filterEvent === (string)$val,
                            'hover:bg-base-200 text-base-content/80' => $filterEvent !== (string)$val,
                        ])
                    >
                        <span class="inline-flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $opt['dot'] }}"></span>
                            {{ $opt['label'] }}
                        </span>
                        @if($filterEvent === (string)$val)
                            <x-mary-icon name="o-check" class="w-3.5 h-3.5 text-info" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Dropdown Periode / Waktu --}}
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            @php
                $periodLabels = [
                    '' => 'Semua Waktu',
                    'today' => 'Hari Ini',
                    'yesterday' => 'Kemarin',
                    '7days' => '7 Hari Terakhir',
                    '30days' => '30 Hari Terakhir',
                    'month' => 'Bulan Ini',
                    'last_month' => 'Bulan Lalu',
                    '3months' => '3 Bulan Terakhir',
                    'custom' => 'Rentang Tanggal',
                ];
                $currentPeriodLabel = $periodLabels[$filterPeriod] ?? 'Semua Waktu';
                if ($filterPeriod === 'custom' && $filterStartDate && $filterEndDate) {
                    $currentPeriodLabel = \Carbon\Carbon::parse($filterStartDate)->format('d M') . ' - ' . \Carbon\Carbon::parse($filterEndDate)->format('d M');
                }
            @endphp

            <button
                type="button"
                @click="open = !open"
                @class([
                    'btn btn-ghost btn-sm rounded-full gap-2 text-xs font-normal border transition-all duration-200 [background-image:none] shadow-none',
                    '!bg-warning/15 !text-warning !border-warning/40 font-semibold' => !empty($filterPeriod),
                    'border-base-300 bg-base-100/60 hover:bg-base-200 text-base-content/80' => empty($filterPeriod),
                ])
            >
                <x-mary-icon name="o-calendar" class="w-3.5 h-3.5 opacity-70" />
                <span>{{ $currentPeriodLabel }}</span>
                <x-mary-icon name="o-chevron-down" class="w-3 h-3 opacity-50 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
            </button>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                class="absolute left-0 top-full mt-2 z-30 min-w-52 p-1.5 bg-base-100/95 backdrop-blur-md rounded-2xl shadow-xl border border-base-300/80 space-y-0.5"
                style="display: none;"
            >
                @foreach(['' => 'Semua Waktu', 'today' => 'Hari Ini', 'yesterday' => 'Kemarin', '7days' => '7 Hari Terakhir', '30days' => '30 Hari Terakhir', 'month' => 'Bulan Ini', 'last_month' => 'Bulan Lalu', '3months' => '3 Bulan Terakhir'] as $val => $label)
                    <button
                        type="button"
                        wire:click="$set('filterPeriod', '{{ $val }}')"
                        @click="open = false"
                        @class([
                            'w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors',
                            'bg-warning/15 text-base-content font-semibold' => $filterPeriod === $val,
                            'hover:bg-base-200 text-base-content/80' => $filterPeriod !== $val,
                        ])
                    >
                        <span>{{ $label }}</span>
                        @if($filterPeriod === $val)
                            <x-mary-icon name="o-check" class="w-3.5 h-3.5 text-warning" />
                        @endif
                    </button>
                @endforeach

                <div class="border-t border-base-200 my-1"></div>

                <button
                    type="button"
                    wire:click="$set('filterPeriod', 'custom')"
                    @click="open = false"
                    @class([
                        'w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors',
                        'bg-warning/15 text-base-content font-semibold' => $filterPeriod === 'custom',
                        'hover:bg-base-200 text-base-content/80' => $filterPeriod !== 'custom',
                    ])
                >
                    <span class="inline-flex items-center gap-1.5">
                        <x-mary-icon name="o-adjustments-horizontal" class="w-3.5 h-3.5 opacity-60" />
                        Pilih Rentang Tanggal...
                    </span>
                    @if($filterPeriod === 'custom')
                        <x-mary-icon name="o-check" class="w-3.5 h-3.5 text-warning" />
                    @endif
                </button>
            </div>
        </div>

        @if($filterPeriod === 'custom')
            <div class="flex items-center gap-1.5 bg-base-100 px-3 py-1 rounded-full border border-base-300 shadow-xs">
                <input
                    type="date"
                    wire:model.live="filterStartDate"
                    class="bg-transparent text-xs text-base-content focus:outline-none cursor-pointer"
                    title="Dari Tanggal"
                />
                <span class="text-xs text-base-content/40 font-medium">s/d</span>
                <input
                    type="date"
                    wire:model.live="filterEndDate"
                    class="bg-transparent text-xs text-base-content focus:outline-none cursor-pointer"
                    title="Sampai Tanggal"
                />
            </div>
        @endif

        @if($search || $filterLogName || $filterEvent || $filterPeriod || $filterStartDate || $filterEndDate)
            <button
                type="button"
                wire:click="resetFilters"
                class="btn btn-ghost btn-xs rounded-full text-error gap-1 hover:bg-error/10"
                title="Reset semua filter"
            >
                <x-mary-icon name="o-x-mark" class="w-3.5 h-3.5" />
                Reset Filter
            </button>
        @endif
    </div>

    {{-- Tabel Log Aktivitas --}}
    <x-table-card :rows="$this->activities" empty-icon="o-clipboard-document-list" empty-text="Belum ada aktivitas yang tercatat">
        <x-slot:head>
            <th class="w-44">Waktu</th>
            <th class="w-48">Pengguna (Pelaku)</th>
            <th class="w-36">Modul</th>
            <th class="w-28">Tindakan</th>
            <th>Keterangan</th>
            <th class="w-20 text-center pe-3">Detail</th>
        </x-slot:head>

        @foreach($this->activities as $activity)
            @php
                $causer = $activity->causer;
                $hasChanges = !empty($activity->properties['attributes']) || !empty($activity->properties['old']);

                // Styling Modul selaras dengan category-pill (dot + soft pill)
                $moduleStyle = match($activity->log_name) {
                    'laporan'        => ['pill' => 'bg-primary/15 text-primary border-primary/20',     'dot' => 'bg-primary',         'label' => 'Laporan'],
                    'pelanggan'      => ['pill' => 'bg-secondary/15 text-secondary border-secondary/20', 'dot' => 'bg-secondary',     'label' => 'Pelanggan'],
                    'pengguna'       => ['pill' => 'bg-accent/15 text-accent border-accent/20',       'dot' => 'bg-accent',          'label' => 'Pengguna'],
                    'paket-internet' => ['pill' => 'bg-warning/15 text-warning border-warning/20',   'dot' => 'bg-warning',         'label' => 'Paket Internet'],
                    'ip-pool'        => ['pill' => 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/20', 'dot' => 'bg-purple-500', 'label' => 'IP Pool'],
                    default          => ['pill' => 'bg-base-200 text-base-content/70 border-base-300', 'dot' => 'bg-base-content/40', 'label' => ucfirst(str_replace('-', ' ', $activity->log_name ?? 'Umum'))],
                };

                // Styling Tindakan (Event)
                $eventStyle = match($activity->event) {
                    'created' => ['pill' => 'bg-success/15 text-success border-success/20', 'label' => 'Created'],
                    'updated' => ['pill' => 'bg-info/15 text-info border-info/20',          'label' => 'Updated'],
                    'deleted' => ['pill' => 'bg-error/15 text-error border-error/20',        'label' => 'Deleted'],
                    default   => ['pill' => 'bg-base-content/10 text-base-content/70 border-base-300', 'label' => ucfirst($activity->event ?? 'Action')],
                };
            @endphp
            <tr class="hover:bg-base-200/60 transition-colors">
                {{-- Waktu --}}
                <td>
                    <div class="flex flex-col">
                        <span class="text-xs font-medium text-base-content">
                            {{ $activity->created_at->translatedFormat('d M Y, H:i:s') }}
                        </span>
                        <span class="text-[10px] text-base-content/50">
                            {{ $activity->created_at->diffForHumans() }}
                        </span>
                    </div>
                </td>

                {{-- Pengguna / Pelaku --}}
                <td>
                    @if($causer)
                        <div class="flex items-center gap-2">
                            <div class="avatar avatar-placeholder shrink-0">
                                <div class="w-7 h-7 rounded-full bg-primary/20 text-primary font-bold text-[10px] flex items-center justify-center">
                                    {{ strtoupper(substr($causer->name, 0, 2)) }}
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-base-content truncate">
                                    {{ $causer->name }}
                                </p>
                                <p class="text-[10px] text-base-content/50 truncate">
                                    {{ $causer->role ?? 'User' }}
                                </p>
                            </div>
                        </div>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs text-base-content/40 italic">
                            <x-mary-icon name="o-cpu-chip" class="w-3.5 h-3.5" />
                            Sistem / Guest
                        </span>
                    @endif
                </td>

                {{-- Modul / Log Name (Dot + Soft Pill) --}}
                <td>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $moduleStyle['pill'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $moduleStyle['dot'] }}"></span>
                        {{ $moduleStyle['label'] }}
                    </span>
                </td>

                {{-- Tindakan / Event --}}
                <td>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $eventStyle['pill'] }}">
                        {{ $eventStyle['label'] }}
                    </span>
                </td>

                {{-- Keterangan / Description --}}
                <td>
                    <div class="text-xs text-base-content/80 max-w-md">
                        <span class="font-medium text-base-content">{{ $activity->description }}</span>
                        @if($activity->subject_type)
                            <span class="text-base-content/40 text-[11px] block truncate">
                                Target: {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                            </span>
                        @endif
                    </div>
                </td>

                {{-- Aksi / Detail (Tooltip ke kiri agar tidak terpotong di tepi layar) --}}
                <td class="text-center pe-3">
                    @if($hasChanges)
                        <div class="inline-block tooltip tooltip-left" data-tip="Lihat Detail">
                            <button
                                type="button"
                                wire:click="showDetail({{ $activity->id }})"
                                class="btn btn-ghost btn-xs btn-circle text-primary hover:bg-primary/10 transition-colors"
                            >
                                <x-mary-icon name="o-eye" class="w-4 h-4" />
                            </button>
                        </div>
                    @else
                        <span class="text-base-content/30 text-xs">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-table-card>

    {{-- Modal Rincian Perubahan Data (Diff) --}}
    <x-mary-modal wire:model="showDetailModal" title="Rincian Audit Perubahan Data" separator class="backdrop-blur-sm">
        @if($this->selectedActivity)
            @php
                $act = $this->selectedActivity;
                $attributes = (array) ($act->properties['attributes'] ?? []);
                $old = (array) ($act->properties['old'] ?? []);
                $allKeys = array_unique(array_merge(array_keys($old), array_keys($attributes)));
                sort($allKeys);
            @endphp

            <div class="space-y-4">
                {{-- Info Ringkas Aktivitas --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-3 bg-base-200/70 rounded-xl text-xs">
                    <div>
                        <span class="text-base-content/50 block text-[10px] uppercase font-bold">Waktu</span>
                        <span class="font-semibold">{{ $act->created_at->translatedFormat('d M Y, H:i:s') }}</span>
                    </div>
                    <div>
                        <span class="text-base-content/50 block text-[10px] uppercase font-bold">Pelaku</span>
                        <span class="font-semibold">{{ $act->causer?->name ?? 'Sistem' }}</span>
                    </div>
                    <div>
                        <span class="text-base-content/50 block text-[10px] uppercase font-bold">Modul</span>
                        <span class="font-semibold capitalize">{{ str_replace('-', ' ', $act->log_name ?? 'Umum') }}</span>
                    </div>
                    <div>
                        <span class="text-base-content/50 block text-[10px] uppercase font-bold">Tindakan</span>
                        <span class="font-semibold capitalize">{{ $act->event ?? 'Log' }}</span>
                    </div>
                </div>

                {{-- Keterangan --}}
                <div class="text-sm font-medium text-base-content bg-primary/5 border border-primary/15 p-3 rounded-xl">
                    <p class="text-xs text-primary font-bold uppercase mb-0.5">Keterangan:</p>
                    <p>{{ $act->description }}</p>
                </div>

                {{-- Tabel Perbandingan Nilai (Diff) --}}
                @if(!empty($allKeys))
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-base-content/60 mb-2">
                            Perubahan Field / Nilai
                        </h4>
                        <div class="overflow-x-auto border border-base-300 rounded-xl">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr class="bg-base-200/60 text-base-content/60 text-[10px] uppercase">
                                        <th class="w-1/3">Field</th>
                                        <th class="w-1/3">Nilai Sebelum</th>
                                        <th class="w-1/3">Nilai Sesudah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($allKeys as $key)
                                        @php
                                            $valOld = $old[$key] ?? null;
                                            $valNew = $attributes[$key] ?? null;

                                            $formatVal = function($val) {
                                                if (is_null($val)) return '<span class="text-base-content/30 italic">null</span>';
                                                if (is_bool($val)) return $val ? 'true' : 'false';
                                                if (is_array($val)) return json_encode($val, JSON_UNESCAPED_UNICODE);
                                                return e((string) $val);
                                            };
                                        @endphp
                                        <tr class="hover:bg-base-200/40">
                                            <td class="font-mono text-xs font-semibold text-base-content">
                                                {{ $key }}
                                            </td>
                                            <td class="text-xs">
                                                @if(array_key_exists($key, $old))
                                                    <span class="px-1.5 py-0.5 rounded bg-error/15 text-error font-mono break-all inline-block">
                                                        {!! $formatVal($valOld) !!}
                                                    </span>
                                                @else
                                                    <span class="text-base-content/30 italic">—</span>
                                                @endif
                                            </td>
                                            <td class="text-xs">
                                                @if(array_key_exists($key, $attributes))
                                                    <span class="px-1.5 py-0.5 rounded bg-success/15 text-success font-mono break-all inline-block">
                                                        {!! $formatVal($valNew) !!}
                                                    </span>
                                                @else
                                                    <span class="text-base-content/30 italic">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="text-center py-6 text-base-content/50 text-xs italic">
                        Tidak ada catatan perubahan nilai atribut spesifik pada aktivitas ini.
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Tutup" class="btn-ghost rounded-full" wire:click="$set('showDetailModal', false)" />
        </x-slot:actions>
    </x-mary-modal>
</div>
