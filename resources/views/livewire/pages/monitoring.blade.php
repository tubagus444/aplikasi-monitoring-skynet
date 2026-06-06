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
    <x-mary-header title="Monitoring GPS" separator class="!mb-6">
        <x-slot:actions>
            <x-badge value="Live" class="badge-info" />
            <span class="text-xs text-base-content/40 hidden sm:inline">Refresh tiap 10 detik</span>
        </x-slot:actions>
    </x-mary-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Sidebar: daftar teknisi aktif --}}
        <div class="lg:col-span-1">
            <x-mary-card class="h-full">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-semibold">Teknisi Aktif</p>
                    <x-badge value="{{ count($technicianLocations) }}" class="badge-primary badge-sm" />
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
                                class="p-3 rounded-lg bg-base-200 hover:bg-base-300 cursor-pointer transition-colors"
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
        <div class="lg:col-span-2">
            <x-mary-card class="!p-0 overflow-hidden">
                <div
                    x-data="monitoringMap(@json($technicianLocations))"
                    x-init="init()"
                    @locations-updated.window="updateMarkers($event.detail.locations)"
                    @focus-technician.window="focusMarker($event.detail.id)"
                >
                    <div x-ref="map" class="h-[520px] w-full"></div>
                </div>
            </x-mary-card>
        </div>

    </div>
</div>

@assets
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endassets

<script>
function monitoringMap(initialLocations) {
    return {
        map: null,
        markers: {},

        init() {
            this.map = L.map(this.$refs.map).setView([-6.37, 107.16], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            this.$nextTick(() => {
                this.map.invalidateSize();
                this.updateMarkers(initialLocations || []);
            });
        },

        updateMarkers(locations) {
            if (!this.map) return;

            const activeIds = locations
                .filter(l => l.latitude && l.longitude)
                .map(l => String(l.id));

            // Hapus marker teknisi yang sudah tidak aktif
            Object.keys(this.markers).forEach(id => {
                if (!activeIds.includes(id)) {
                    this.map.removeLayer(this.markers[id]);
                    delete this.markers[id];
                }
            });

            const markerList = [];

            locations.forEach(loc => {
                if (!loc.latitude || !loc.longitude) return;

                const popup = `
                    <div style="min-width:160px;font-family:inherit;line-height:1.4">
                        <p style="font-weight:600;margin:0 0 4px 0;font-size:13px">${loc.name}</p>
                        <p style="margin:0;font-size:12px;color:#555">${loc.customer}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#888">${loc.damage_type}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#aaa;word-break:break-word">${loc.address}</p>
                        ${loc.last_update ? `<p style="margin:6px 0 0;font-size:11px;color:#aaa">&#128205; ${loc.last_update}</p>` : ''}
                    </div>`;

                const id = String(loc.id);

                if (this.markers[id]) {
                    this.markers[id].setLatLng([loc.latitude, loc.longitude]);
                    this.markers[id].getPopup().setContent(popup);
                    markerList.push(this.markers[id]);
                } else {
                    const marker = L.marker([loc.latitude, loc.longitude])
                        .bindPopup(popup)
                        .addTo(this.map);
                    this.markers[id] = marker;
                    markerList.push(marker);
                }
            });

            if (markerList.length === 1) {
                this.map.setView(markerList[0].getLatLng(), 15);
            } else if (markerList.length > 1) {
                const group = L.featureGroup(markerList);
                this.map.fitBounds(group.getBounds().pad(0.3));
            }
        },

        focusMarker(techId) {
            const marker = this.markers[String(techId)];
            if (marker) {
                this.map.setView(marker.getLatLng(), 17);
                marker.openPopup();
            }
        },
    };
}
</script>
