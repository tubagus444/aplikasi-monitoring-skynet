<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="skynet">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'SkyNet Admin') }}</title>
        {{-- Theme: apply on load (anti-flash) and after wire:navigate --}}
        <script>
            function skynetApplyTheme() {
                const t = localStorage.getItem('skynet-theme') || 'skynet';
                document.documentElement.setAttribute('data-theme', t);
            }
            // Global: dipanggil native onclick di tombol toggle tema (navigation.blade.php).
            // Tidak bergantung pada scope Alpine, jadi tetap jalan di komponen Livewire nested + wire:navigate.
            window.skynetToggleTheme = function () {
                const cur = document.documentElement.getAttribute('data-theme');
                const next = cur === 'skynet-dark' ? 'skynet' : 'skynet-dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('skynet-theme', next);
            };
            skynetApplyTheme();
            document.addEventListener('livewire:navigated', skynetApplyTheme);
        </script>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    </head>
    <body class="font-sans antialiased">

        <x-toast />

        {{-- Topbar (mobile only) --}}
        <x-nav sticky full-width class="bg-base-100 border-b border-base-300 lg:hidden">
            <x-slot:brand>
                <label for="main-drawer" class="cursor-pointer mr-2">
                    <x-mary-icon name="o-bars-3" class="w-6 h-6 text-base-content/70" />
                </label>
                <span class="font-bold text-base-content/90">SkyNet Admin</span>
            </x-slot:brand>
        </x-nav>

        {{-- Main layout --}}
        <x-main full-width collapsible>

            <x-slot:sidebar drawer="main-drawer" class="bg-base-100 border-r border-base-300">
                <livewire:layout.navigation />
            </x-slot:sidebar>

            <x-slot:content class="p-0!">
                <div class="p-6 lg:p-8 bg-base-200 min-h-screen">
                    {{ $slot }}
                </div>
            </x-slot:content>

        </x-main>

    </body>
</html>
