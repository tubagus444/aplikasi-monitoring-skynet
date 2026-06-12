<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_teknisi_dapat_login_dengan_kredensial_valid(): void
    {
        $teknisi = User::factory()->teknisi()->create();

        $response = $this->postJson('/api/auth/login', [
            'email'    => $teknisi->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        $teknisi = User::factory()->teknisi()->create();

        $this->postJson('/api/auth/login', [
            'email'    => $teknisi->email,
            'password' => 'salah',
        ])->assertUnauthorized();
    }

    public function test_admin_tidak_dapat_login_via_api(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/auth/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_teknisi_dapat_logout(): void
    {
        $teknisi = User::factory()->teknisi()->create();
        $token   = $teknisi->createToken('android')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logout berhasil']);
    }

    public function test_teknisi_dapat_update_fcm_token(): void
    {
        $teknisi = User::factory()->teknisi()->create();

        $this->actingAs($teknisi, 'sanctum')
            ->putJson('/api/auth/fcm-token', ['fcm_token' => 'token-fcm-contoh'])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id'        => $teknisi->id,
            'fcm_token' => 'token-fcm-contoh',
        ]);
    }

    public function test_endpoint_api_butuh_autentikasi(): void
    {
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/location')->assertUnauthorized();
    }

    public function test_login_api_dibatasi_rate_limit(): void
    {
        $teknisi = User::factory()->teknisi()->create();

        // 5 percobaan gagal masih dilayani (401)
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/login', [
                'email'    => $teknisi->email,
                'password' => 'salah',
            ])->assertUnauthorized();
        }

        // Percobaan ke-6 diblokir throttle (429 Too Many Requests)
        $this->postJson('/api/auth/login', [
            'email'    => $teknisi->email,
            'password' => 'salah',
        ])->assertStatus(429);
    }
}
