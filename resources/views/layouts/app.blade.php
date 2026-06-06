<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'SkyNet Admin') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">

        {{-- Topbar (mobile only) --}}
        <x-nav sticky full-width class="border-b border-base-300 lg:hidden">
            <x-slot:brand>
                <label for="main-drawer" class="cursor-pointer mr-2">
                    <x-mary-icon name="o-bars-3" class="w-6 h-6" />
                </label>
                <span class="font-semibold text-base-content">SkyNet Admin</span>
            </x-slot:brand>
        </x-nav>

        {{-- Main layout --}}
        <x-main full-width collapsible>

            <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200 border-r border-base-300">
                <livewire:layout.navigation />
            </x-slot:sidebar>

            <x-slot:content class="!p-0">
                <div class="p-6 lg:p-8">
                    {{ $slot }}
                </div>
            </x-slot:content>

        </x-main>

    </body>
</html>
