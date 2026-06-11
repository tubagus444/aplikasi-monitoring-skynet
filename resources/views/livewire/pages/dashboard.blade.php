<?php

use App\Enums\ReportStatus;
use App\Models\DamageReport;
use App\Models\LocationLog;
use App\Models\TaskAssignment;
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
        return DamageReport::where('status', ReportStatus::Ditugaskan->value)->count();
    }

    #[Computed]
    public function sedangDikerjakan(): int
    {
        return DamageReport::where('status', ReportStatus::SedangMemperbaiki->value)->count();
    }

    #[Computed]
    public function selesaiHariIni(): int
    {
        return DamageReport::where('status', ReportStatus::Selesai->value)
            ->whereDate('updated_at', today())
            ->count();
    }

    #[Computed]
    public function selesaiKemarin(): int
    {
        return DamageReport::where('status', ReportStatus::Selesai->value)
            ->whereDate('updated_at', today()->subDay())
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

    public function refreshMap(): void
    {
        unset($this->mapLocations);
        $this->dispatch('map-data-refreshed', locations: $this->mapLocations);
    }

    #[Computed]
    public function mapLocations(): array
    {
        $assignments = TaskAssignment::with(['technician', 'report.damageType'])
            ->whereHas('report', fn($q) => $q->where('status', ReportStatus::SedangMemperbaiki->value))
            ->get();

        $seen   = [];
        $result = [];

        foreach ($assignments as $assignment) {
            $techId = $assignment->technician_id;
            if (in_array($techId, $seen)) continue;
            $seen[] = $techId;

            $latest = LocationLog::where('technician_id', $techId)
                ->where('report_id', $assignment->report_id)
                ->latest('recorded_at')
                ->first();

            if (! $latest) continue;

            $result[] = [
                'id'          => $techId,
                'name'        => $assignment->technician->name,
                'customer'    => $assignment->report->customer_name,
                'damage_type' => $assignment->report->damageType->name,
                'latitude'    => $latest->latitude,
                'longitude'   => $latest->longitude,
            ];
        }

        return $result;
    }
}; ?>

<div>
    <x-mary-header title="Dashboard" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button icon="o-arrow-path" class="btn-ghost btn-sm rounded-full" label="Refresh" wire:click="$refresh" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

        @php
            // Chip ikon memakai warna solid (bg-* + *-content) untuk kontras kuat.
            // Tulis kelas warna sebagai literal lengkap agar terdeteksi scanner Tailwind.
            $stats = [
                ['label' => 'Total Laporan',     'value' => $this->totalLaporan,    'sub' => $this->laporan_ditugaskan . ' belum ditugaskan', 'icon' => 'o-document-text',      'chip' => 'bg-primary text-primary-content'],
                ['label' => 'Sedang Dikerjakan', 'value' => $this->sedangDikerjakan, 'sub' => 'laporan aktif',                                 'icon' => 'o-wrench-screwdriver', 'chip' => 'bg-info text-info-content'],
                ['label' => 'Selesai Hari Ini',  'value' => $this->selesaiHariIni,   'sub' => 'kemarin: ' . $this->selesaiKemarin,            'icon' => 'o-check-circle',       'chip' => 'bg-success text-success-content'],
                ['label' => 'Total Teknisi',     'value' => $this->totalTeknisi,     'sub' => 'teknisi terdaftar',                             'icon' => 'o-user-group',         'chip' => 'bg-secondary text-secondary-content'],
            ];
        @endphp

        @foreach($stats as $stat)
            <div class="bg-base-100 rounded-2xl p-5 border border-base-200 hover:border-base-300 hover:shadow-sm transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-base-content/50">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-semibold text-base-content mt-1.5">{{ $stat['value'] }}</p>
                        <p class="text-xs text-base-content/40 mt-1 truncate">{{ $stat['sub'] }}</p>
                    </div>
                    <div class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center {{ $stat['chip'] }}">
                        <x-mary-icon name="{{ $stat['icon'] }}" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        @endforeach

    </div>

    {{-- Peta + laporan terbaru --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <x-mary-card title="Peta GPS Teknisi Aktif" class="rounded-2xl">
            <x-slot:menu>
                <x-mary-button icon="o-arrow-path" class="btn-ghost btn-xs rounded-full" wire:click="refreshMap" wire:loading.attr="disabled" wire:target="refreshMap" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-info/15 text-info">
                    <span class="w-1.5 h-1.5 rounded-full bg-info animate-pulse"></span>
                    {{ count($this->mapLocations) }} aktif
                </span>
            </x-slot:menu>
            {{-- Tinggi tetap (h-96 / 384px): peta = elemen utama, ukurannya disengaja & konsisten, lepas dari tinggi card sebelah --}}
            <div wire:ignore class="h-96">
                @if(count($this->mapLocations) === 0)
                    <div id="dashboard-map-placeholder" class="h-full bg-base-200 rounded-xl flex flex-col items-center justify-center gap-2 border border-dashed border-base-300">
                        <x-mary-icon name="o-map-pin" class="w-8 h-8 text-base-content/30" />
                        <p class="text-sm text-base-content/40">Tidak ada teknisi aktif</p>
                        <a href="{{ route('monitoring') }}" wire:navigate class="text-xs text-primary hover:underline">Buka halaman Monitoring →</a>
                    </div>
                @else
                    <div id="dashboard-map" class="h-full rounded-xl overflow-hidden w-full"></div>
                @endif
            </div>
        </x-mary-card>

        <x-mary-card title="Laporan Terbaru" class="rounded-2xl">
            @if($this->laporanTerbaru->isEmpty())
                <div class="text-center py-8 text-base-content/40">
                    <p class="text-sm">Belum ada laporan</p>
                </div>
            @else
                <div class="flex flex-col divide-y divide-base-200">
                    @foreach($this->laporanTerbaru as $report)
                        @php
                            // Laporan belum selesai dan sudah >24 jam dianggap urgen
                            $isUrgen = $report->status !== \App\Enums\ReportStatus::Selesai->value && $report->created_at->diffInHours(now()) > 24;
                        @endphp
                        <div class="flex items-center justify-between py-3 gap-3 {{ $isUrgen ? 'opacity-100' : '' }}">
                            <div class="min-w-0 flex items-start gap-2">
                                @if($isUrgen)
                                    <span class="mt-0.5 shrink-0 w-1 h-full self-stretch min-h-8 rounded-full bg-error/60"></span>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-base-content truncate">{{ $report->customer_name }}</p>
                                    <p class="text-xs font-medium text-base-content/60 truncate">{{ $report->damageType->name }}</p>
                                    <p class="text-xs text-base-content/35 mt-0.5">{{ $report->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-col items-end gap-1.5">
                                <x-status-pill :status="$report->status" pulse />
                                @if($isUrgen)
                                    <span class="text-[10px] text-error/70 font-medium">Perlu perhatian</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    <a href="{{ route('reports.index') }}" wire:navigate class="btn btn-soft btn-primary btn-sm btn-block rounded-full">
                        Lihat semua laporan
                        <x-mary-icon name="o-arrow-right" class="w-4 h-4" />
                    </a>
                </div>
            @endif
        </x-mary-card>

    </div>
</div>

@if(count($this->mapLocations) > 0)
    @script
    <script>
        (function () {
            const el = document.getElementById('dashboard-map');
            if (!el || el._leaflet_id) return;

            const map = L.map(el, { zoomControl: true, attributionControl: true })
                .setView([-6.37, 107.16], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(map);

            const markers = {};

            function updateMarkers(locations) {
                const activeIds = locations.map(l => String(l.id));
                Object.keys(markers).forEach(id => {
                    if (!activeIds.includes(id)) {
                        map.removeLayer(markers[id]);
                        delete markers[id];
                    }
                });
                const markerList = [];
                locations.forEach(loc => {
                    const popup = `
                        <div style="min-width:140px;line-height:1.4">
                            <p style="font-weight:600;margin:0 0 2px;font-size:13px">${loc.name}</p>
                            <p style="margin:0;font-size:12px;color:#555">${loc.customer}</p>
                            <p style="margin:2px 0 0;font-size:11px;color:#888">${loc.damage_type}</p>
                        </div>`;
                    const id = String(loc.id);
                    if (markers[id]) {
                        markers[id].setLatLng([loc.latitude, loc.longitude]);
                        markers[id].getPopup().setContent(popup);
                    } else {
                        markers[id] = L.marker([loc.latitude, loc.longitude])
                            .bindPopup(popup)
                            .addTo(map);
                    }
                    markerList.push(markers[id]);
                });
                return markerList;
            }

            setTimeout(() => {
                map.invalidateSize();
                const markerList = updateMarkers(@json($this->mapLocations));
                if (markerList.length === 1) {
                    map.setView(markerList[0].getLatLng(), 15);
                } else if (markerList.length > 1) {
                    map.fitBounds(L.featureGroup(markerList).getBounds().pad(0.3));
                }
            }, 100);

            $wire.on('map-data-refreshed', ({ locations }) => {
                updateMarkers(locations);
            });
        })();
    </script>
    @endscript
@endif
