<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;
    
    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        if (! auth()->user()->isAdmin()) {
        auth()->logout();
        
        $this->addError('form.email', 'Akses web hanya untuk admin.');
        return;
    }

    $this->redirectIntended(
        default: route('dashboard', absolute: false),
        navigate: true
    );
    }
}; ?>

<div>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-base-content">Masuk ke Panel Admin</h2>
        <p class="text-sm text-base-content/50 mt-1">Khusus untuk administrator SkyNet</p>
    </div>

    @if(session('status'))
        <div class="mb-4 p-3 rounded-lg bg-base-200 border border-base-300">
            <p class="text-sm text-success font-medium">{{ session('status') }}</p>
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">

        <div>
            <x-mary-input
                label="Email"
                wire:model="form.email"
                type="email"
                id="email"
                placeholder="admin@skynet.test"
                icon="o-envelope"
                required
                autofocus
                autocomplete="username"
            />
            @error('form.email')
                <p class="text-xs text-error mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div x-data="{ show: false }">
            <x-mary-input
                label="Password"
                wire:model="form.password"
                x-bind:type="show ? 'text' : 'password'"
                type="password"
                id="password"
                placeholder="••••••••"
                icon="o-lock-closed"
                required
                autocomplete="current-password"
            >
                <x-slot:append>
                    <button
                        type="button"
                        @click="show = !show"
                        tabindex="-1"
                        class="btn btn-ghost join-item border-0 px-3"
                        :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                    >
                        <span x-show="!show"><x-mary-icon name="o-eye" class="w-4 h-4 opacity-50" /></span>
                        <span x-show="show" x-cloak><x-mary-icon name="o-eye-slash" class="w-4 h-4 opacity-50" /></span>
                    </button>
                </x-slot:append>
            </x-mary-input>
            @error('form.password')
                <p class="text-xs text-error mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2">
            <x-mary-button
                label="Masuk"
                type="submit"
                class="btn-primary w-full"
                spinner="login"
            />
        </div>

    </form>

    {{-- Link Halus Teknisi di Bawah Form --}}
    <div class="mt-5 pt-4 border-t border-base-300/70 text-center">
        <button type="button" 
                onclick="download_modal.showModal()" 
                class="text-xs text-base-content/60 hover:text-primary transition-colors inline-flex items-center gap-1.5 cursor-pointer">
            <x-mary-icon name="o-device-phone-mobile" class="w-4 h-4 text-primary" />
            <span>Teknisi Lapangan? <span class="font-semibold underline">Unduh Aplikasi</span></span>
        </button>
    </div>

    {{-- Floating Pill Button di Pojok Kanan Bawah --}}
    <div class="fixed bottom-5 right-5 z-40">
    <button type="button" 
            onclick="download_modal.showModal()"
            class="btn btn-sm shadow-md rounded-full bg-base-100 border-base-300 hover:bg-base-200 text-base-content gap-2 px-3.5 h-10 border transition-all hover:scale-105">
        <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
        <x-mary-icon name="o-device-phone-mobile" class="w-4 h-4 text-primary" />
        <span class="text-xs font-semibold">App Teknisi (APK)</span>
    </button>
</div>

{{-- Modal Popup Download APK & QR Code (Modern App Card) --}}
<dialog id="download_modal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box max-w-sm sm:max-w-xl p-0 overflow-hidden border border-base-300 shadow-2xl rounded-3xl bg-base-100">
        
        {{-- Header Modal --}}
        <div class="p-6 pb-5 bg-gradient-to-b from-primary/5 via-transparent to-transparent border-b border-base-300/60 relative">
            {{-- Tombol Close --}}
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4 text-base-content/60 hover:text-base-content hover:bg-base-200">✕</button>
            </form>

            <div class="flex items-start gap-4 pr-8">
                {{-- App Icon --}}
                <div class="w-13 h-13 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-br from-primary to-blue-600 flex items-center justify-center text-white shadow-lg shadow-primary/25 shrink-0 ring-4 ring-primary/10">
                    <svg class="w-7 h-7 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                    </svg>
                </div>

                {{-- App Title & Badges --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h3 class="text-base sm:text-lg font-bold text-base-content tracking-tight">SkyNet Monitoring</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                            v1.0 Rilis
                        </span>
                    </div>
                    <p class="text-xs text-base-content/60 mb-2">Aplikasi Khusus Teknisi Lapangan SkyNet RT/RW Net</p>

                    <div class="flex items-center gap-2.5 text-[11px] text-base-content/60">
                        <span class="inline-flex items-center gap-1 font-medium">
                            <x-mary-icon name="o-cpu-chip" class="w-3.5 h-3.5 opacity-60" />
                            Android 7.0+
                        </span>
                        <span>&bull;</span>
                        <span class="inline-flex items-center gap-1 font-medium">
                            <x-mary-icon name="o-circle-stack" class="w-3.5 h-3.5 opacity-60" />
                            14.9 MB
                        </span>
                        <span>&bull;</span>
                        <span class="text-emerald-600 font-medium">Ready</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Body Content: Split 2 Kolom di Desktop / Stack di Mobile --}}
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
            {{-- Kolom Kiri: Fitur & Tombol Download --}}
            <div class="flex flex-col justify-between h-full space-y-4">
                <div>
                    <p class="text-[11px] font-bold text-base-content/60 uppercase tracking-wider mb-2.5">Fitur Utama Aplikasi:</p>
                    <ul class="space-y-2 text-xs text-base-content/75">
                        <li class="flex items-center gap-2">
                            <x-mary-icon name="o-check-circle" class="w-4 h-4 text-emerald-500 shrink-0" />
                            <span>Penerimaan tugas perbaikan realtime</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-mary-icon name="o-check-circle" class="w-4 h-4 text-emerald-500 shrink-0" />
                            <span>Pelacakan lokasi GPS teknisi ke admin</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-mary-icon name="o-check-circle" class="w-4 h-4 text-emerald-500 shrink-0" />
                            <span>Upload bukti foto perbaikan langsung</span>
                        </li>
                    </ul>
                </div>

                <div class="pt-2">
                    <a href="{{ route('download.apk') }}"
                       class="btn btn-primary w-full shadow-md shadow-primary/20 gap-2 font-semibold h-11 text-sm"
                       download>
                        <x-mary-icon name="o-arrow-down-tray" class="w-4 h-4" />
                        Unduh File APK
                    </a>
                    <p class="text-[11px] text-center text-base-content/40 mt-1.5">
                        Klik untuk unduh langsung di HP ini
                    </p>
                </div>
            </div>

            {{-- Kolom Kanan: Card QR Code --}}
            <div class="bg-base-200/50 rounded-2xl p-4 border border-base-300 text-center flex flex-col items-center justify-center">
                <p class="text-xs font-semibold text-base-content/75 mb-2.5">Scan via Kamera HP</p>
                
                {{-- QR Code Frame --}}
                <div class="bg-white p-2.5 rounded-2xl shadow-sm border border-base-300 inline-block transition-transform hover:scale-102">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=4&data={{ urlencode(route('download.apk')) }}"
                         alt="QR Code Unduh APK Monitoring Teknisi"
                         class="w-32 h-32"
                         loading="lazy" />
                </div>

                <p class="text-[11px] text-base-content/50 mt-2.5 leading-tight">
                    Arahkan kamera ponsel ke QR Code untuk mengunduh instan
                </p>
            </div>
        </div>

        {{-- Footer Note --}}
        <div class="px-6 py-3 bg-base-200/40 border-t border-base-300/60 flex items-center justify-between text-[11px] text-base-content/50">
            <span>SkyNet RT/RW Net &middot; Mobile Client</span>
            <span class="inline-flex items-center gap-1.5 font-medium text-emerald-600">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                Official Release
            </span>
        </div>

    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
</div>
