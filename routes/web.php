<?php

use App\Http\Controllers\CustomerExportController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('reports', 'pages.laporan.index')->name('reports.index');
    Volt::route('monitoring', 'pages.monitoring')->name('monitoring');
    Volt::route('notifikasi', 'pages.notifikasi')->name('notifikasi');

    Route::get('history/export', [ReportExportController::class, 'riwayat'])->name('history.export');
    Volt::route('statistik', 'pages.statistik')->name('statistik');
    Volt::route('customers', 'pages.pelanggan.index')->name('customers.index');
    Route::get('customers/export/pdf', [CustomerExportController::class, 'pdf'])->name('customers.export.pdf');
    Route::get('customers/export/excel', [CustomerExportController::class, 'excel'])->name('customers.export.excel');
    Volt::route('customers/{customer}', 'pages.pelanggan.detail')->name('customers.show');
    Volt::route('ip-pools', 'pages.ip-pool.index')->name('ip-pools.index');
    Volt::route('damage-types', 'pages.jenis-gangguan.index')->name('damage-types.index');
    Volt::route('internet-packages', 'pages.paket-internet.index')->name('internet-packages.index');
    Volt::route('users', 'pages.pengguna.index')->name('users.index');
    Volt::route('activity-logs', 'pages.log-aktivitas.index')->name('activity-logs.index');
});

require __DIR__.'/auth.php';
