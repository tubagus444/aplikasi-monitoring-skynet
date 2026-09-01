<?php

namespace App\Observers;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class NotificationObserver implements ShouldQueue
{
    public function created(Notification $notification): void
    {
        // Ambil hanya kolom fcm_token via query relasi — tidak menghidrasi model
        // User penuh & tidak bergantung pada eager-load (Notification sengaja TIDAK
        // memuat relasi user secara global; endpoint daftar notifikasi tak butuh).
        $fcmToken = $notification->user()->value('fcm_token');

        if (! $fcmToken) {
            return;
        }

        try {
            $message = CloudMessage::new()
                ->withToken($fcmToken)
                ->withNotification(FcmNotification::create($notification->title, $notification->body));

            app(Messaging::class)->send($message);
        } catch (\Throwable $e) {
            Log::warning('FCM send failed', [
                'notification_id' => $notification->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
