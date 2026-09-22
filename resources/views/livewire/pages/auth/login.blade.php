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

    {{-- Khusus Teknisi Lapangan: Unduh APK Mobile & QR Code --}}
    <div class="divider my-6 text-[11px] text-base-content/40 font-semibold tracking-wider uppercase">
        Teknisi Lapangan
    </div>

    <div class="bg-base-200/60 rounded-xl p-4 border border-base-300 text-center">
        <div class="flex items-center justify-center gap-2 mb-1">
            <x-mary-icon name="o-device-phone-mobile" class="w-5 h-5 text-primary" />
            <h3 class="text-sm font-bold text-base-content">Aplikasi Android Teknisi</h3>
        </div>
        <p class="text-xs text-base-content/60 mb-3">
            Gunakan aplikasi mobile untuk menerima tugas perbaikan & pengiriman GPS realtime.
        </p>

        <a href="{{ route('download.apk') }}"
           class="btn btn-outline btn-primary btn-sm w-full gap-2 font-medium"
           download>
            <x-mary-icon name="o-arrow-down-tray" class="w-4 h-4" />
            Unduh APK (v1.0)
        </a>

        <div class="mt-3 pt-3 border-t border-base-300/60 flex flex-col items-center">
            <p class="text-[11px] text-base-content/60 mb-2 font-medium">Scan untuk unduh langsung di HP:</p>
            <div class="bg-white p-2 rounded-xl shadow-xs border border-base-300 inline-block">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&margin=4&data={{ urlencode(route('download.apk')) }}"
                     alt="QR Code Unduh APK Monitoring Teknisi"
                     class="w-28 h-28"
                     loading="lazy" />
            </div>
            <span class="text-[10px] text-base-content/40 mt-1.5">Arahkan kamera HP ke QR Code di atas</span>
        </div>
    </div>
</div>
