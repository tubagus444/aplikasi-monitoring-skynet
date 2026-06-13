<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Hardening: proteksi "tak boleh hapus akun sendiri" harus ditegakkan di
 * deleteUser(), bukan hanya disembunyikan di confirmDelete(). Properti Livewire
 * (deletingId) bisa di-set langsung dari browser, jadi memanggil deleteUser tanpa
 * lewat confirmDelete tidak boleh berhasil menghapus diri sendiri.
 */
class PenggunaDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tidak_bisa_hapus_akun_sendiri_walau_lewati_confirm_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Volt::test('pages.pengguna.index')
            ->set('deletingId', $admin->id) // simulasi manipulasi klien, lewati confirmDelete
            ->call('deleteUser')
            ->assertSet('showDeleteModal', false);

        // Akun sendiri tetap ada — guard menahan penghapusan.
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_bisa_hapus_pengguna_lain(): void
    {
        $admin = User::factory()->admin()->create();
        $lain  = User::factory()->teknisi()->create();
        $this->actingAs($admin);

        Volt::test('pages.pengguna.index')
            ->call('confirmDelete', $lain->id)
            ->assertSet('deletingName', $lain->name)
            ->call('deleteUser')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('users', ['id' => $lain->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
