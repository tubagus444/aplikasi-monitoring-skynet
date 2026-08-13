<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $selectedTab = 'aktif';
}; ?>

<div class="flex flex-col h-full">
    {{-- Header Utama --}}
    <x-mary-header title="Manajemen Laporan" separator class="mb-2!" />

    {{-- Tabs (Alpine + DaisyUI) --}}
    <div x-data="{ tab: @entangle('selectedTab') }" class="w-full">
        <div role="tablist" class="tabs tabs-boxed bg-base-200/50 p-1 rounded-xl mb-6 inline-flex border border-base-300">
            <a role="tab" class="tab rounded-lg px-6 font-medium transition-all" 
               :class="{ 'tab-active bg-base-100 text-primary shadow-sm': tab === 'aktif' }" 
               @click="tab = 'aktif'">
                <x-mary-icon name="o-document-text" class="w-4 h-4 mr-2 inline-block" />
                Laporan Aktif
            </a>
            <a role="tab" class="tab rounded-lg px-6 font-medium transition-all" 
               :class="{ 'tab-active bg-base-100 text-primary shadow-sm': tab === 'riwayat' }" 
               @click="tab = 'riwayat'">
                <x-mary-icon name="o-clock" class="w-4 h-4 mr-2 inline-block" />
                Riwayat Selesai
            </a>
        </div>

        {{-- Tab Contents --}}
        <div x-show="tab === 'aktif'" style="display: none;" x-transition.opacity>
            <livewire:pages.laporan.aktif />
        </div>
        
        <div x-show="tab === 'riwayat'" style="display: none;" x-transition.opacity>
            <livewire:pages.laporan.riwayat />
        </div>
    </div>
</div>
