<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col min-h-screen bg-base-100">

    {{-- Header: logo text (left) + hamburger (right) --}}
    <div class="px-4 py-3 border-b border-base-300 flex items-center">

        <a href="{{ route('dashboard') }}" wire:navigate class="mary-hideable flex-1 flex flex-col leading-tight min-w-0">
            <span class="text-sm font-bold text-base-content whitespace-nowrap">SkyNet</span>
            <span class="text-xs text-base-content/40 whitespace-nowrap">RT/RW Net &middot; Admin</span>
        </a>

        <button
            @click="toggle()"
            class="hidden lg:flex w-8 h-8 items-center justify-center rounded-lg text-base-content/40 hover:text-base-content/70 hover:bg-base-content/[0.06] transition-colors flex-shrink-0"
            title="Toggle sidebar"
        >
            <x-mary-icon name="o-bars-3" class="w-4 h-4" />
        </button>

    </div>

    {{-- Navigasi --}}
    <x-mary-menu activate-by-route class="flex-1 p-2 mt-1">
        <x-mary-menu-item title="Dashboard"  icon="o-squares-2x2"       route="dashboard"    />
        <x-mary-menu-item title="Laporan"    icon="o-exclamation-circle" route="reports.index" />
        <x-mary-menu-item title="Monitoring" icon="o-map-pin"            route="monitoring"   />
        <x-mary-menu-item title="Riwayat"    icon="o-clock"              route="history"      />
        <x-mary-menu-item title="Statistik"  icon="o-chart-bar"          route="statistik"    />
        <x-mary-menu-item title="Pelanggan"  icon="o-identification"     route="customers.index" />
        <x-mary-menu-item title="Jenis Gangguan" icon="o-wrench-screwdriver" route="damage-types.index" />
        <x-mary-menu-item title="Pengguna"   icon="o-users"              route="users.index"  />
    </x-mary-menu>

    {{-- Footer: avatar + name + theme toggle + logout --}}
    <div class="border-t border-base-300 px-3 py-3 mt-auto">
        <div class="flex items-center gap-3">

            <div class="avatar avatar-placeholder flex-shrink-0">
                <div class="w-9 h-9 rounded-lg bg-primary text-primary-content flex items-center justify-center">
                    <span class="text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </span>
                </div>
            </div>

            <div class="mary-hideable flex-1 min-w-0">
                <p class="text-sm font-semibold text-base-content/80 truncate">
                    {{ auth()->user()->name }}
                </p>
                <button wire:click="logout" class="text-xs text-base-content/40 hover:text-error transition-colors cursor-pointer font-medium">
                    Keluar
                </button>
            </div>

            {{--
                Native onclick → window.skynetToggleTheme() (didefinisikan di layouts.app).
                Tidak bergantung scope Alpine, jadi andal di komponen Livewire nested & survive wire:navigate.
                Ikon dikendalikan CSS: .theme-icon-moon/.theme-icon-sun bereaksi ke data-theme di <html>.
            --}}
            <button
                type="button"
                onclick="skynetToggleTheme()"
                class="mary-hideable shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-base-content/40 hover:text-base-content/70 hover:bg-base-content/[0.06] transition-colors"
                title="Toggle tema"
            >
                <span class="theme-icon-moon"><x-mary-icon name="o-moon" class="w-4 h-4" /></span>
                <span class="theme-icon-sun"><x-mary-icon name="o-sun"  class="w-4 h-4" /></span>
            </button>

        </div>
    </div>

</div>
