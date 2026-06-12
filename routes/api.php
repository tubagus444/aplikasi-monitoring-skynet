<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

// Autentikasi — throttle cegah brute-force (login web sudah dibatasi 5x via LoginForm)
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    // Autentikasi
    Route::post('/auth/logout',         [AuthController::class, 'logout']);
    Route::put('/auth/fcm-token',       [AuthController::class, 'updateFcmToken']);

    // Tugas teknisi
    Route::get('/tasks',                [TaskController::class, 'index']);
    Route::get('/tasks/{id}',           [TaskController::class, 'show']);
    Route::post('/tasks/{id}/status',   [TaskController::class, 'updateStatus']);

    // GPS
    Route::post('/location',            [LocationController::class, 'store']);

    // Notifikasi
    Route::get('/notifications',                    [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read',          [NotificationController::class, 'markAsRead']);
});
