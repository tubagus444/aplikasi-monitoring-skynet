<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    // fcm_token null di factory → observer return early, tidak hit FCM
    private function buatNotifikasi(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id' => $user->id,
            'title'   => 'Tugas Baru',
            'body'    => 'Anda ditugaskan ke laporan baru.',
        ], $overrides));
    }

    public function test_teknisi_dapat_melihat_notifikasi(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $this->buatNotifikasi($teknisi);
        $this->buatNotifikasi($teknisi);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'body', 'is_read', 'created_at']],
            ]);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_teknisi_hanya_melihat_notifikasi_miliknya(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $lain    = User::factory()->teknisi()->create();

        $this->buatNotifikasi($teknisi);
        $this->buatNotifikasi($lain);

        $response = $this->actingAs($teknisi, 'sanctum')->getJson('/api/notifications');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_teknisi_dapat_tandai_notifikasi_sudah_dibaca(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $notifikasi = $this->buatNotifikasi($teknisi)->fresh();

        $this->assertFalse($notifikasi->is_read);

        $this->actingAs($teknisi, 'sanctum')
            ->putJson("/api/notifications/{$notifikasi->id}/read")
            ->assertOk();

        $this->assertTrue($notifikasi->fresh()->is_read);
    }

    public function test_teknisi_tidak_dapat_tandai_notifikasi_milik_orang_lain(): void
    {
        $teknisi    = User::factory()->teknisi()->create();
        $lain       = User::factory()->teknisi()->create();
        $notifikasi = $this->buatNotifikasi($lain);

        $this->actingAs($teknisi, 'sanctum')
            ->putJson("/api/notifications/{$notifikasi->id}/read")
            ->assertNotFound();
    }
}
