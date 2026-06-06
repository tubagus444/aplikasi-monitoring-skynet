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

<div class="flex flex-col h-full">

    {{-- Logo --}}
    <div class="p-4 border-b border-base-300">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
            <x-mary-icon name="o-wifi" class="w-7 h-7 text-primary flex-shrink-0" />
            <div class="mary-hideable leading-tight">
                <div class="font-semibold text-base-content">SkyNet</div>
                <div class="text-xs text-base-content/50">RT/RW Net &middot; Admin</div>
            </div>
        </a>
    </div>

    {{-- Navigasi --}}
    <x-mary-menu activate-by-route class="flex-1 p-2 mt-1">
        <x-mary-menu-item title="Dashboard"  icon="o-squares-2x2"      route="dashboard"    />
        <x-mary-menu-item title="Laporan"    icon="o-exclamation-circle" route="reports.index" />
        <x-mary-menu-item title="Monitoring" icon="o-map-pin"           route="monitoring"   />
        <x-mary-menu-item title="Riwayat"    icon="o-clock"             route="history"      />
        <x-mary-menu-item title="Pengguna"   icon="o-users"             route="users.index"  />
    </x-mary-menu>

    {{-- User info + Logout --}}
    <div class="border-t border-base-300 p-3 mt-auto">
        <div class="flex items-center gap-2">
            <div class="avatar placeholder flex-shrink-0">
                <div class="w-8 rounded-full bg-primary text-primary-content">
                    <span class="text-xs font-semibold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </span>
                </div>
            </div>
            <div class="mary-hideable flex-1 min-w-0">
                <p class="text-sm font-medium text-base-content truncate">
                    {{ auth()->user()->name }}
                </p>
                <button wire:click="logout" class="text-xs text-base-content/50 hover:text-error transition-colors cursor-pointer">
                    Logout
                </button>
            </div>
        </div>
    </div>

</div>
