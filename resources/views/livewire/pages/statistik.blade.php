<?php

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /** Pecahan jumlah laporan per kategori (semua status). DB-agnostic. */
    #[Computed]
    public function pecahanKategori(): array
    {
        $counts = DamageReport::selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return array_map(fn (ReportCategory $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'total' => (int) ($counts[$c->value] ?? 0),
        ], ReportCategory::cases());
    }

    #[Computed]
    public function totalLaporan(): int
    {
        return DamageReport::count();
    }

    #[Computed]
    public function jumlahSelesai(): int
    {
        return DamageReport::where('status', ReportStatus::Selesai->value)->count();
    }

    /** Rata-rata durasi penanganan (menit) laporan selesai; null bila belum ada. */
    #[Computed]
    public function rataDurasiMenit(): ?int
    {
        $reports = DamageReport::where('status', ReportStatus::Selesai->value)
            ->get(['created_at', 'completed_at', 'updated_at']);

        if ($reports->isEmpty()) {
            return null;
        }

        $total = $reports->sum(fn ($r) => abs($r->created_at->diffInMinutes($r->waktu_selesai)));

        return (int) round($total / $reports->count());
    }

    /** Format durasi menit → teks ringkas (hari/jam/menit), sejajar durasiPenanganan(). */
    public function formatDurasi(?int $menit): string
    {
        if ($menit === null) {
            return '—';
        }

        return match (true) {
            $menit >= 1440 => round($menit / 1440, 1) . ' hari',
            $menit >= 60   => round($menit / 60, 1) . ' jam',
            default        => $menit . ' menit',
        };
    }

    /** Tren komplain 12 bulan terakhir (urut, isi 0 untuk bulan kosong). */
    #[Computed]
    public function trenBulanan(): array
    {
        \Carbon\Carbon::setLocale('id');
        $start = now()->subMonths(11)->startOfMonth();

        $counts = DamageReport::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($r) => $r->created_at->format('Y-m'))
            ->map->count();

        $bulan = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $bulan[] = [
                'label' => $m->translatedFormat('M y'),
                'total' => (int) ($counts[$m->format('Y-m')] ?? 0),
            ];
        }

        return $bulan;
    }

    /** Kinerja per teknisi: jumlah tugas ditangani & yang laporannya sudah selesai. */
    #[Computed]
    public function kinerjaTeknisi()
    {
        return User::where('role', UserRole::Teknisi->value)
            ->withCount([
                'taskAssignments as tugas_ditangani',
                'taskAssignments as tugas_selesai' => fn ($q) =>
                    $q->whereHas('report', fn ($r) => $r->where('status', ReportStatus::Selesai->value)),
            ])
            ->orderByDesc('tugas_ditangani')
            ->get();
    }

    /** Lima jenis kerusakan paling sering dilaporkan. */
    #[Computed]
    public function kerusakanTersering()
    {
        return DamageType::withCount('damageReports')
            ->orderByDesc('damage_reports_count')
            ->limit(5)
            ->get();
    }
}; ?>

<div>
    <x-mary-header title="Statistik" subtitle="Analitik laporan & kinerja teknisi" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button icon="o-arrow-path" class="btn-ghost btn-sm rounded-full" label="Refresh" wire:click="$refresh" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Stat cards: pecahan kategori + rata-rata durasi --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach($this->pecahanKategori as $kat)
            @php
                // Literal lengkap agar terdeteksi scanner Tailwind.
                $chip = match($kat['value']) {
                    'pelanggan'    => 'bg-primary text-primary-content',
                    'jaringan'     => 'bg-info text-info-content',
                    'pemeliharaan' => 'bg-secondary text-secondary-content',
                    default        => 'bg-base-300 text-base-content',
                };
                $icon = match($kat['value']) {
                    'pelanggan'    => 'o-user',
                    'jaringan'     => 'o-signal',
                    'pemeliharaan' => 'o-wrench-screwdriver',
                    default        => 'o-tag',
                };
            @endphp
            <div class="bg-base-100 rounded-2xl p-5 border border-base-200 hover:border-base-300 hover:shadow-sm transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-base-content/50 truncate">{{ $kat['label'] }}</p>
                        <p class="text-2xl font-semibold text-base-content mt-1.5">{{ $kat['total'] }}</p>
                        <p class="text-xs text-base-content/40 mt-1">laporan</p>
                    </div>
                    <div class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center {{ $chip }}">
                        <x-mary-icon name="{{ $icon }}" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        @endforeach

        <div class="bg-base-100 rounded-2xl p-5 border border-base-200 hover:border-base-300 hover:shadow-sm transition">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-base-content/50 truncate">Rata-rata Durasi</p>
                    <p class="text-2xl font-semibold text-base-content mt-1.5">{{ $this->formatDurasi($this->rataDurasiMenit) }}</p>
                    <p class="text-xs text-base-content/40 mt-1">{{ $this->jumlahSelesai }} laporan selesai</p>
                </div>
                <div class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-success text-success-content">
                    <x-mary-icon name="o-clock" class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Grafik tren komplain per bulan --}}
    <x-mary-card title="Tren Komplain per Bulan" subtitle="12 bulan terakhir" class="rounded-2xl mb-4">
        <div wire:ignore class="h-72">
            <canvas id="tren-chart"></canvas>
        </div>
    </x-mary-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Kinerja per teknisi --}}
        <x-mary-card title="Kinerja Teknisi" class="rounded-2xl">
            @if($this->kinerjaTeknisi->isEmpty())
                <div class="text-center py-8 text-base-content/40">
                    <p class="text-sm">Belum ada teknisi</p>
                </div>
            @else
                <div class="overflow-x-auto no-scrollbar">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-xs text-base-content/50">
                                <th>Teknisi</th>
                                <th class="text-center w-24">Ditangani</th>
                                <th class="text-center w-20">Selesai</th>
                                <th class="w-32">Rasio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->kinerjaTeknisi as $tek)
                                @php
                                    $rasio = $tek->tugas_ditangani > 0
                                        ? round($tek->tugas_selesai / $tek->tugas_ditangani * 100)
                                        : 0;
                                @endphp
                                <tr class="hover:bg-base-200 transition-colors">
                                    <td class="font-medium text-sm">{{ $tek->name }}</td>
                                    <td class="text-center text-sm">{{ $tek->tugas_ditangani }}</td>
                                    <td class="text-center text-sm text-success font-medium">{{ $tek->tugas_selesai }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-1.5 rounded-full bg-base-200 overflow-hidden">
                                                <div class="h-full rounded-full bg-success" style="width: {{ $rasio }}%"></div>
                                            </div>
                                            <span class="text-xs text-base-content/50 w-9 text-right">{{ $rasio }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-mary-card>

        {{-- Kerusakan tersering --}}
        <x-mary-card title="Kerusakan Tersering" subtitle="Top 5" class="rounded-2xl">
            @php $maxKerusakan = $this->kerusakanTersering->max('damage_reports_count') ?: 1; @endphp
            @if($this->kerusakanTersering->where('damage_reports_count', '>', 0)->isEmpty())
                <div class="text-center py-8 text-base-content/40">
                    <p class="text-sm">Belum ada data kerusakan</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->kerusakanTersering as $jenis)
                        @continue($jenis->damage_reports_count === 0)
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-sm font-medium truncate">{{ $jenis->name }}</p>
                                <span class="text-xs text-base-content/50 shrink-0">{{ $jenis->damage_reports_count }} laporan</span>
                            </div>
                            <div class="h-2 rounded-full bg-base-200 overflow-hidden">
                                <div class="h-full rounded-full bg-info"
                                     style="width: {{ round($jenis->damage_reports_count / $maxKerusakan * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-mary-card>

    </div>

    @script
    <script>
        (function () {
            const el = document.getElementById('tren-chart');
            if (!el || el._chartInit) return;
            el._chartInit = true;

            const data = @json($this->trenBulanan);
            const styles = getComputedStyle(document.documentElement);
            const primary = (styles.getPropertyValue('--color-primary') || '#2563EB').trim();

            new Chart(el, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'Komplain',
                        data: data.map(d => d.total),
                        borderColor: primary,
                        backgroundColor: primary,
                        tension: 0.3,
                        fill: false,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                    },
                },
            });
        })();
    </script>
    @endscript
</div>
