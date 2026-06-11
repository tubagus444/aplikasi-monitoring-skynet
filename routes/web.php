<?php

use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('reports', 'pages.laporan.index')->name('reports.index');
    Volt::route('monitoring', 'pages.monitoring')->name('monitoring');
    Volt::route('history', 'pages.riwayat')->name('history');
    Route::get('history/export', [ReportExportController::class, 'riwayat'])->name('history.export');
    Volt::route('users', 'pages.pengguna.index')->name('users.index');
});

require __DIR__.'/auth.php';
