<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="skynet">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SkyNet Admin') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-base-200 min-h-screen flex items-center justify-center">

        <div class="w-full max-w-sm px-4">

            {{-- Logo / brand mark --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-primary mb-4">
                    <svg class="w-7 h-7 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-base-content">SkyNet Admin</h1>
                <p class="text-sm text-base-content/50 mt-1">RT/RW Net &middot; Network Monitoring</p>
            </div>

            {{-- Card --}}
            <div class="bg-base-100 rounded-2xl p-8 shadow-sm border border-base-300">
                {{ $slot }}
            </div>

        </div>

    </body>
</html>
