<?php

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

    #[Computed]
    public function mapLocations(): array
    {
        $assignments = TaskAssignment::with(['technician', 'report.damageType'])
            ->whereHas('report', fn($q) => $q->where('status', 'sedang_memperbaiki'))
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
    <x-mary-header title="Dashboard" separator class="!mb-6">
        <x-slot:actions>
            <x-mary-button icon="o-arrow-path" class="btn-ghost btn-sm" label="Refresh" wire:click="$refresh" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

        @php
            // Catatan: modifier opacity (mis. bg-primary/10) TIDAK ter-generate untuk warna
            // tema DaisyUI di setup Tailwind v3 ini — hanya warna solid yang berfungsi.
            // Karena itu chip ikon pakai warna solid + *-content. Kelas ditulis lengkap
            // sebagai literal agar terdeteksi scanner Tailwind JIT.
            $stats = [
                ['label' => 'Total Laporan',     'value' => $this->totalLaporan,    'sub' => $this->laporan_ditugaskan . ' belum ditugaskan', 'icon' => 'o-document-text',      'chip' => 'bg-primary text-primary-content'],
                ['label' => 'Sedang Dikerjakan', 'value' => $this->sedangDikerjakan, 'sub' => 'laporan aktif',                                 'icon' => 'o-wrench-screwdriver', 'chip' => 'bg-info text-info-content'],
                ['label' => 'Selesai Hari Ini',  'value' => $this->selesaiHariIni,   'sub' => now()->translatedFormat('d F Y'),                'icon' => 'o-check-circle',       'chip' => 'bg-success text-success-content'],
                ['label' => 'Total Teknisi',     'value' => $this->totalTeknisi,     'sub' => 'teknisi terdaftar',                             'icon' => 'o-user-group',         'chip' => 'bg-secondary text-secondary-content'],
            ];
        @endphp

        @foreach($stats as $stat)
            <div class="bg-base-100 rounded-xl p-5 border border-base-200 hover:border-base-300 hover:shadow-sm transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-base-content/50">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-semibold text-base-content mt-1.5">{{ $stat['value'] }}</p>
                        <p class="text-xs text-base-content/40 mt-1 truncate">{{ $stat['sub'] }}</p>
                    </div>
                    <div class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center {{ $stat['chip'] }}">
                        <x-mary-icon name="{{ $stat['icon'] }}" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        @endforeach

    </div>

    {{-- Peta + laporan terbaru --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <x-mary-card title="Peta GPS Teknisi Aktif">
            <x-slot:menu>
                <x-badge value="{{ count($this->mapLocations) }} aktif" class="badge-info badge-sm" />
            </x-slot:menu>
            <div wire:ignore>
                @if(count($this->mapLocations) === 0)
                    <div id="dashboard-map-placeholder" class="bg-base-200 rounded-lg flex flex-col items-center justify-center gap-2 border border-dashed border-base-300" style="height:224px;">
                        <x-mary-icon name="o-map-pin" class="w-8 h-8 text-base-content/30" />
                        <p class="text-sm text-base-content/40">Tidak ada teknisi aktif</p>
                        <a href="{{ route('monitoring') }}" wire:navigate class="text-xs text-primary hover:underline">Buka halaman Monitoring →</a>
                    </div>
                @else
                    <div id="dashboard-map" class="rounded-lg overflow-hidden" style="height:224px;width:100%;"></div>
                @endif
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

            const locations = @json($this->mapLocations);
            const markerList = [];

            locations.forEach(loc => {
                const popup = `
                    <div style="min-width:140px;line-height:1.4">
                        <p style="font-weight:600;margin:0 0 2px;font-size:13px">${loc.name}</p>
                        <p style="margin:0;font-size:12px;color:#555">${loc.customer}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#888">${loc.damage_type}</p>
                    </div>`;

                const marker = L.marker([loc.latitude, loc.longitude])
                    .bindPopup(popup)
                    .addTo(map);

                markerList.push(marker);
            });

            setTimeout(() => {
                map.invalidateSize();
                if (markerList.length === 1) {
                    map.setView(markerList[0].getLatLng(), 15);
                } else {
                    map.fitBounds(L.featureGroup(markerList).getBounds().pad(0.3));
                }
            }, 100);
        })();
    </script>
    @endscript
@endif
