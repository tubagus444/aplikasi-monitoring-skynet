<?php

use App\Actions\GetActiveTechnicianLocations;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $technicianLocations = [];

    // Modal galeri foto bukti pekerjaan (dibuka dari strip thumbnail sidebar)
    public bool $showPhotoModal = false;
    public array $selectedPhotos = [];
    public string $selectedTechName = '';

    public function mount(): void
    {
        $this->loadLocations();
    }

    public function loadLocations(): void
    {
        $this->technicianLocations = (new GetActiveTechnicianLocations)();
        $this->dispatch('locations-updated', locations: $this->technicianLocations);
    }

    public function openPhotos(int $id): void
    {
        $tech = collect($this->technicianLocations)->firstWhere('id', $id);

        if (! $tech || empty($tech['photos'])) {
            return;
        }

        $this->selectedPhotos = $tech['photos'];
        $this->selectedTechName = $tech['name'];
        $this->showPhotoModal = true;
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
                                    @if($tech['latitude'] && $tech['is_stale'])
                                        <span class="inline-flex items-center gap-1 text-xs text-warning">
                                            <span class="w-1.5 h-1.5 rounded-full bg-warning inline-block"></span>
                                            GPS terputus
                                        </span>
                                    @elseif($tech['latitude'])
                                        <span class="inline-flex items-center gap-1 text-xs text-success">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse inline-block"></span>
                                            GPS aktif
                                        </span>
                                    @else
                                        <span class="text-xs text-base-content/30">Menunggu GPS</span>
                                    @endif
                                </div>
                                @if(!empty($tech['tasks']) && count($tech['tasks']) > 1)
                                    <div class="mb-1.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-primary/10 text-primary mb-1.5">
                                            {{ count($tech['tasks']) }} Tugas Aktif
                                        </span>
                                        <div class="flex flex-col gap-1">
                                            @foreach($tech['tasks'] as $task)
                                                <div class="p-1.5 rounded-lg bg-base-200/60 border border-base-300/40 text-xs">
                                                    <p class="font-medium text-base-content/80 truncate">{{ $task['customer'] }}</p>
                                                    @if($task['damage_type'])
                                                        <p class="text-[11px] text-base-content/50 truncate">{{ $task['damage_type'] }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="text-xs text-base-content/60 truncate">{{ $tech['customer'] }}</p>
                                    <p class="text-xs text-base-content/40">{{ $tech['damage_type'] }}</p>
                                @endif
                                @if($tech['last_update'])
                                    <p class="text-xs text-base-content/30 mt-1">&#128205; {{ $tech['last_update'] }}</p>
                                @endif

                                {{-- Foto bukti pekerjaan terbaru — strip thumbnail, klik buka galeri.
                                     stopPropagation agar tidak ikut memicu focus-technician (klik kartu). --}}
                                @if(!empty($tech['photos']))
                                    <div
                                        class="flex items-center gap-1.5 mt-2"
                                        x-on:click.stop="$wire.openPhotos({{ $tech['id'] }})"
                                    >
                                        @foreach(array_slice($tech['photos'], 0, 3) as $photo)
                                            <img src="{{ $photo['url'] }}" alt="Foto bukti"
                                                 class="w-9 h-9 rounded-lg object-cover border border-base-300" />
                                        @endforeach
                                        @if(count($tech['photos']) > 3)
                                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-base-200 text-xs font-medium text-base-content/60 border border-base-300">
                                                +{{ count($tech['photos']) - 3 }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-primary ml-0.5">Lihat foto</span>
                                    </div>
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

    {{-- Modal galeri foto bukti pekerjaan (teknisi yang sedang memperbaiki) --}}
    <x-mary-modal wire:model="showPhotoModal" :title="'Foto Bukti — ' . $selectedTechName" separator box-class="max-w-3xl">
        @if(empty($selectedPhotos))
            <p class="text-sm text-base-content/50 py-6 text-center">Belum ada foto.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($selectedPhotos as $photo)
                    <a href="{{ $photo['url'] }}" target="_blank" class="block">
                        <img src="{{ $photo['url'] }}" alt="Foto bukti"
                             class="w-full h-32 rounded-xl object-cover border border-base-300 hover:opacity-90 transition-opacity" />
                        @if($photo['caption'])
                            <p class="text-xs text-base-content/50 mt-1 truncate">{{ $photo['caption'] }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
        <x-slot:actions>
            <x-mary-button label="Tutup" class="btn-ghost rounded-full" wire:click="$set('showPhotoModal', false)" />
        </x-slot:actions>
    </x-mary-modal>
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

        // Palet warna marker — dipilih konsisten per id teknisi (id sama → warna sama
        // selama sesi), jadi tiap teknisi punya identitas warna tetap di peta.
        const MARKER_COLORS = ['#2563eb', '#0891b2', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#ca8a04', '#dc2626'];
        const STALE_COLOR = '#94a3b8'; // GPS basi → abu, marker meredup vs teknisi live

        function colorFor(loc) {
            if (loc.is_stale) return STALE_COLOR;
            const n = Math.abs(parseInt(loc.id, 10)) || 0;
            return MARKER_COLORS[n % MARKER_COLORS.length];
        }

        function initialsFor(name) {
            if (!name) return '?';
            const parts = name.trim().split(/\s+/);
            const first = parts[0]?.[0] ?? '';
            const second = parts.length > 1 ? parts[parts.length - 1][0] : '';
            return (first + second).toUpperCase() || '?';
        }

        // Marker = lingkaran berinisial + segitiga penunjuk lurus ke bawah (tip = lokasi
        // tepat). Style inline karena divIcon di-render di pane peta, bukan dipindai Tailwind.
        function makeIcon(loc) {
            const color = colorFor(loc);
            const html = `
                <div style="position:relative;width:34px;height:40px">
                    <div style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,.35)">
                        <span style="color:#fff;font-weight:700;font-size:12px;font-family:ui-sans-serif,system-ui,sans-serif;line-height:1">${initialsFor(loc.name)}</span>
                    </div>
                    <div style="position:absolute;left:50%;top:30px;transform:translateX(-50%);width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;border-top:8px solid ${color}"></div>
                </div>`;
            return L.divIcon({
                html,
                className: 'technician-marker', // override default agar tak ada kotak putih leaflet-div-icon
                iconSize: [34, 40],
                iconAnchor: [17, 38],   // tip segitiga = titik lokasi
                popupAnchor: [0, -36],
            });
        }

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

                let tasksHtml = '';
                if (loc.tasks && loc.tasks.length > 1) {
                    tasksHtml = `
                        <div style="margin:4px 0 0;padding:4px 0;border-top:1px solid #e5e7eb">
                            <p style="margin:0 0 3px;font-size:11px;font-weight:600;color:#2563eb">${loc.tasks.length} Tugas Aktif:</p>
                            <div style="display:flex;flex-direction:column;gap:3px;max-height:120px;overflow-y:auto">
                                ${loc.tasks.map(t => `
                                    <div style="font-size:11px;line-height:1.2;background:#f9fafb;padding:3px 5px;border-radius:4px;border:1px solid #f3f4f6">
                                        <strong style="color:#111">${t.customer}</strong>
                                        <div style="color:#666;font-size:10px">${t.damage_type || ''}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>`;
                } else {
                    tasksHtml = `
                        <p style="margin:0;font-size:12px;color:#555">${loc.customer || ''}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#888">${loc.damage_type || ''}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#aaa">${loc.address || ''}</p>`;
                }

                const popup = `
                    <div style="min-width:170px;max-width:240px;line-height:1.4">
                        <p style="font-weight:600;margin:0 0 4px;font-size:13px">${loc.name}</p>
                        ${tasksHtml}
                        ${loc.last_update ? `<p style="margin:6px 0 0;font-size:11px;color:#aaa">&#128205; ${loc.last_update}</p>` : ''}
                    </div>`;

                const id = String(loc.id);

                if (markers[id]) {
                    markers[id].setLatLng([loc.latitude, loc.longitude]);
                    markers[id].getPopup().setContent(popup);
                } else {
                    markers[id] = L.marker([loc.latitude, loc.longitude], { icon: makeIcon(loc) })
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
