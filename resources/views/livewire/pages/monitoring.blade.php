<?php

use App\Models\LocationLog;
use App\Models\TaskAssignment;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $technicianLocations = [];

    public function mount(): void
    {
        $this->loadLocations();
    }

    public function loadLocations(): void
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

            $result[] = [
                'id'          => $techId,
                'name'        => $assignment->technician->name,
                'customer'    => $assignment->report->customer_name,
                'address'     => $assignment->report->address,
                'damage_type' => $assignment->report->damageType->name,
                'latitude'    => $latest?->latitude,
                'longitude'   => $latest?->longitude,
                'last_update' => $latest?->recorded_at?->diffForHumans() ?? null,
            ];
        }

        $this->technicianLocations = $result;
        $this->dispatch('locations-updated', locations: $result);
    }
}; ?>

<div wire:poll.10s="loadLocations">
    <x-mary-header title="Monitoring GPS" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button icon="o-arrow-path" class="btn-ghost btn-sm rounded-full" label="Refresh" wire:click="loadLocations" wire:loading.attr="disabled" wire:target="loadLocations" />
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-info/15 text-info">
                <span class="w-1.5 h-1.5 rounded-full bg-info animate-pulse"></span>
                Live
            </span>
            <span class="text-xs text-base-content/40 hidden sm:inline">Refresh tiap 10 detik</span>
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Sidebar: daftar teknisi aktif --}}
        <div class="lg:col-span-1">
            <x-mary-card class="h-full rounded-2xl">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-semibold">Teknisi Aktif</p>
                    <span class="inline-flex items-center justify-center min-w-6 px-2 py-0.5 rounded-full text-xs font-medium bg-primary/15 text-primary">
                        {{ count($technicianLocations) }}
                    </span>
                </div>

                @if(count($technicianLocations) === 0)
                    <div class="text-center py-10 text-base-content/40">
                        <x-mary-icon name="o-map-pin" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                        <p class="text-sm">Tidak ada teknisi aktif</p>
                        <p class="text-xs mt-1 text-base-content/30">Teknisi dengan status<br>"Sedang Memperbaiki"<br>akan muncul di sini</p>
                    </div>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach($technicianLocations as $tech)
                            <div
                                wire:key="sidebar-{{ $tech['id'] }}"
                                class="p-3 rounded-xl bg-base-100 border border-base-300 hover:border-primary/30 hover:bg-base-200/50 cursor-pointer transition-colors"
                                x-on:click="$dispatch('focus-technician', { id: {{ $tech['id'] }} })"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-sm font-medium">{{ $tech['name'] }}</p>
                                    @if($tech['latitude'])
                                        <span class="inline-flex items-center gap-1 text-xs text-success">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse inline-block"></span>
                                            GPS aktif
                                        </span>
                                    @else
                                        <span class="text-xs text-base-content/30">Menunggu GPS</span>
                                    @endif
                                </div>
                                <p class="text-xs text-base-content/60 truncate">{{ $tech['customer'] }}</p>
                                <p class="text-xs text-base-content/40">{{ $tech['damage_type'] }}</p>
                                @if($tech['last_update'])
                                    <p class="text-xs text-base-content/30 mt-1">{{ $tech['last_update'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-mary-card>
        </div>

        {{-- Peta --}}
        <div wire:ignore class="lg:col-span-2">
            <x-mary-card class="p-0! overflow-hidden rounded-2xl">
                <div id="monitoring-map" style="height:520px;width:100%;"></div>
            </x-mary-card>
        </div>

    </div>
</div>

@script
<script>
    (function () {
        const el = document.getElementById('monitoring-map');
        if (!el) return;

        // Inisialisasi peta hanya sekali (cek _leaflet_id mencegah double-init)
        if (el._leaflet_id) return;

        const map = L.map(el).setView([-6.37, 107.16], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        const markers = {};
        let viewInitialized = false;

        function updateMarkers(locations, fitView) {
            const activeIds = locations
                .filter(l => l.latitude && l.longitude)
                .map(l => String(l.id));

            Object.keys(markers).forEach(id => {
                if (!activeIds.includes(id)) {
                    map.removeLayer(markers[id]);
                    delete markers[id];
                }
            });

            const markerList = [];

            locations.forEach(loc => {
                if (!loc.latitude || !loc.longitude) return;

                const popup = `
                    <div style="min-width:160px;line-height:1.4">
                        <p style="font-weight:600;margin:0 0 4px;font-size:13px">${loc.name}</p>
                        <p style="margin:0;font-size:12px;color:#555">${loc.customer}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#888">${loc.damage_type}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#aaa">${loc.address}</p>
                        ${loc.last_update ? `<p style="margin:6px 0 0;font-size:11px;color:#aaa">&#128205; ${loc.last_update}</p>` : ''}
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

            // Fit bounds hanya pada load pertama, bukan saat poll berikutnya
            if (fitView && !viewInitialized && markerList.length > 0) {
                if (markerList.length === 1) {
                    map.setView(markerList[0].getLatLng(), 15);
                } else {
                    map.fitBounds(L.featureGroup(markerList).getBounds().pad(0.3));
                }
                viewInitialized = true;
            }
        }

        // Data awal — fit view ke marker
        setTimeout(() => {
            map.invalidateSize();
            updateMarkers(@json($technicianLocations), true);
        }, 100);

        // Update marker saat poll — tidak reset view
        $wire.on('locations-updated', ({ locations }) => {
            updateMarkers(locations, false);
        });

        // Focus marker saat klik sidebar
        document.addEventListener('focus-technician', (e) => {
            const marker = markers[String(e.detail.id)];
            if (marker) {
                map.setView(marker.getLatLng(), 17);
                marker.openPopup();
            }
        });
    })();
</script>
@endscript
