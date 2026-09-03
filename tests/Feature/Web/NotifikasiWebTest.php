<?php

namespace Tests\Feature\Web;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\DamageReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotifikasiWebTest extends TestCase
{
    use RefreshDatabase;

    private function buatAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function buatNotifikasiAdmin(User $admin, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id'    => $admin->id,
            'title'      => 'Teknisi Mulai Mengerjakan',
            'body'       => 'Budi mulai mengerjakan tugas "Pak Ahmad".',
            'type'       => NotificationType::TaskInProgress->value,
            'related_id' => DamageReport::factory()->create()->id,
        ], $overrides));
    }

    public function test_halaman_notifikasi_render_untuk_admin(): void
    {
        $admin = $this->buatAdmin();

        $this->actingAs($admin)
            ->get(route('notifikasi'))
            ->assertOk()
            ->assertSee('Notifikasi');
    }

    public function test_notifikasi_belum_dibaca_tampil_dengan_badge(): void
    {
        $admin = $this->buatAdmin();
        $this->buatNotifikasiAdmin($admin);
        $this->buatNotifikasiAdmin($admin);

        $response = $this->actingAs($admin)->get(route('notifikasi'));

        $response->assertOk()
            ->assertSee('2 belum dibaca');
    }

    public function test_tandai_dibaca_berfungsi(): void
    {
        $admin = $this->buatAdmin();
        $notif = $this->buatNotifikasiAdmin($admin)->fresh();

        $this->assertFalse($notif->is_read);

        Volt::actingAs($admin)
            ->test('pages.notifikasi')
            ->call('markAsRead', $notif->id);

        $this->assertTrue($notif->fresh()->is_read);
    }

    public function test_tandai_semua_dibaca_berfungsi(): void
    {
        $admin = $this->buatAdmin();
        $this->buatNotifikasiAdmin($admin);
        $this->buatNotifikasiAdmin($admin);
        $this->buatNotifikasiAdmin($admin);

        $this->assertEquals(3, Notification::where('user_id', $admin->id)->where('is_read', false)->count());

        Volt::actingAs($admin)
            ->test('pages.notifikasi')
            ->call('markAllAsRead');

        $this->assertEquals(0, Notification::where('user_id', $admin->id)->where('is_read', false)->count());
    }

    public function test_open_notification_tandai_dibaca(): void
    {
        $admin = $this->buatAdmin();
        $notif = $this->buatNotifikasiAdmin($admin);

        Volt::actingAs($admin)
            ->test('pages.notifikasi')
            ->call('openNotification', $notif->id);

        $this->assertTrue($notif->fresh()->is_read);
    }

    public function test_halaman_kosong_tampil_pesan(): void
    {
        $admin = $this->buatAdmin();

        $response = $this->actingAs($admin)->get(route('notifikasi'));

        $response->assertOk()
            ->assertSee('Belum ada notifikasi');
    }

    public function test_notifikasi_admin_tidak_terlihat_oleh_admin_lain(): void
    {
        $admin1 = $this->buatAdmin();
        $admin2 = User::factory()->admin()->create();

        $this->buatNotifikasiAdmin($admin1);

        // Admin2 tidak melihat notifikasi admin1
        Volt::actingAs($admin2)
            ->test('pages.notifikasi')
            ->assertSee('Belum ada notifikasi');
    }
}
