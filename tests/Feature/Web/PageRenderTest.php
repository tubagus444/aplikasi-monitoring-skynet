<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test halaman web admin: memastikan tiap halaman ter-render (HTTP 200)
 * dan alur root/login berjalan. Menjaga panel admin dari regresi saat refactor.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_dapat_diakses_tamu(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke Panel Admin');
    }

    public function test_root_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_root_mengarahkan_admin_ke_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_semua_halaman_admin_dapat_dirender(): void
    {
        $admin = User::factory()->admin()->create();

        $halaman = [
            '/dashboard'  => 'Dashboard',
            '/reports'    => 'Manajemen Laporan',
            '/monitoring' => 'Monitoring',
            '/history'    => 'Riwayat',
            '/customers'  => 'Pelanggan',
            '/users'      => 'Pengguna',
        ];

        foreach ($halaman as $path => $label) {
            $response = $this->actingAs($admin)->get($path);
            $this->assertEquals(200, $response->status(), "Halaman {$path} ({$label}) gagal dirender");
        }
    }
}
