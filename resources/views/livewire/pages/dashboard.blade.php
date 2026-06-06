<?php

use App\Models\DamageReport;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Computed]
    public function totalLaporan(): int
    {
        return DamageReport::count();
    }

    #[Computed]
    public function laporan_ditugaskan(): int
    {
        return DamageReport::where('status', 'ditugaskan')->count();
    }

    #[Computed]
    public function sedangDikerjakan(): int
    {
        return DamageReport::where('status', 'sedang_memperbaiki')->count();
    }

    #[Computed]
    public function selesaiHariIni(): int
    {
        return DamageReport::where('status', 'selesai')
            ->whereDate('updated_at', today())
            ->count();
    }

    #[Computed]
    public function totalTeknisi(): int
    {
        return User::where('role', 'teknisi')->count();
    }

    #[Computed]
    public function laporanTerbaru()
    {
        return DamageReport::with(['damageType', 'taskAssignments.technician'])
            ->latest()
            ->limit(5)
            ->get();
    }
}; ?>

<div>
    <x-mary-header title="Dashboard" separator class="!mb-6">
        <x-slot:actions>
            <x-mary-button icon="o-arrow-path" class="btn-ghost btn-sm" label="Refresh" wire:click="$refresh" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat
            title="Total Laporan"
            value="{{ $this->totalLaporan }}"
            description="{{ $this->laporan_ditugaskan }} belum ditugaskan"
            icon="o-exclamation-circle"
            class="bg-base-200"
        />
        <x-stat
            title="Sedang Dikerjakan"
            value="{{ $this->sedangDikerjakan }}"
            description="laporan aktif"
            icon="o-clock"
            class="bg-base-200"
        />
        <x-stat
            title="Selesai Hari Ini"
            value="{{ $this->selesaiHariIni }}"
            description="{{ now()->translatedFormat('d F Y') }}"
            icon="o-check-circle"
            class="bg-base-200"
        />
        <x-stat
            title="Total Teknisi"
            value="{{ $this->totalTeknisi }}"
            description="teknisi terdaftar"
            icon="o-users"
            class="bg-base-200"
        />
    </div>

    {{-- Peta + laporan terbaru --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-mary-card title="Peta GPS Teknisi Aktif">
            <x-slot:menu>
                <x-badge value="Live" class="badge-info badge-sm" />
            </x-slot:menu>
            <div class="bg-base-200 rounded-lg h-56 flex flex-col items-center justify-center gap-2 border border-dashed border-base-300">
                <x-mary-icon name="o-map" class="w-10 h-10 text-base-content/30" />
                <p class="text-sm text-base-content/40">Leaflet.js · OpenStreetMap</p>
                <p class="text-xs text-base-content/30">Tersedia di halaman Monitoring</p>
            </div>
        </x-mary-card>

        <x-mary-card title="Laporan Terbaru">
            @if($this->laporanTerbaru->isEmpty())
                <div class="text-center py-8 text-base-content/40">
                    <p class="text-sm">Belum ada laporan</p>
                </div>
            @else
                <div class="flex flex-col divide-y divide-base-200">
                    @foreach($this->laporanTerbaru as $report)
                        @php
                            $badge = match($report->status) {
                                'ditugaskan'         => ['class' => 'badge-warning', 'label' => 'Ditugaskan'],
                                'sedang_memperbaiki' => ['class' => 'badge-info',    'label' => 'Memperbaiki'],
                                'selesai'            => ['class' => 'badge-success', 'label' => 'Selesai'],
                                default              => ['class' => 'badge-ghost',   'label' => $report->status],
                            };
                        @endphp
                        <div class="flex items-center justify-between py-2.5 gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium truncate">{{ $report->customer_name }}</p>
                                <p class="text-xs text-base-content/50 truncate">{{ $report->damageType->name }} · {{ $report->created_at->diffForHumans() }}</p>
                            </div>
                            <x-badge value="{{ $badge['label'] }}" class="badge-sm {{ $badge['class'] }} shrink-0" />
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 pt-3 border-t border-base-200">
                    <a href="{{ route('reports.index') }}" wire:navigate class="text-xs text-primary hover:underline">
                        Lihat semua laporan →
                    </a>
                </div>
            @endif
        </x-mary-card>
    </div>
</div>
