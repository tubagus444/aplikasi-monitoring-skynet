<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\DamageReport;
use App\Models\DamageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Halaman detail pelanggan (modul Pelanggan, batch 4): render, galeri foto rumah
 * (upload/hapus ke disk public), serta ringkasan + riwayat perbaikan per pelanggan.
 */
class PelangganDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_halaman_detail_dapat_dirender(): void
    {
        $customer = Customer::factory()->create(['name' => 'Pak Hendra']);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Pak Hendra')
            ->assertSee('Foto Rumah')
            ->assertSee('Riwayat Perbaikan');
    }

    public function test_unggah_foto_rumah(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();

        Volt::test('pages.pelanggan.detail', ['customer' => $customer])
            ->set('newPhotos', [UploadedFile::fake()->image('rumah.jpg')])
            ->set('caption', 'Tampak depan')
            ->call('uploadPhotos')
            ->assertHasNoErrors();

        $photo = CustomerPhoto::where('customer_id', $customer->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame('Tampak depan', $photo->caption);
        $this->assertSame($this->admin->id, $photo->uploaded_by);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_unggah_menolak_file_bukan_gambar(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();

        Volt::test('pages.pelanggan.detail', ['customer' => $customer])
            ->set('newPhotos', [UploadedFile::fake()->create('dokumen.pdf', 100)])
            ->call('uploadPhotos')
            ->assertHasErrors(['newPhotos.*']);

        $this->assertDatabaseCount('customer_photos', 0);
    }

    public function test_hapus_foto_menghapus_file_dan_baris(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        Storage::disk('public')->put('customer-photos/contoh.jpg', 'dummy');
        $photo = CustomerPhoto::create([
            'customer_id' => $customer->id,
            'uploaded_by' => $this->admin->id,
            'path'        => 'customer-photos/contoh.jpg',
        ]);

        Volt::test('pages.pelanggan.detail', ['customer' => $customer])
            ->call('confirmDeletePhoto', $photo->id)
            ->assertSet('showPhotoDeleteModal', true)
            ->assertSet('deletingPhotoId', $photo->id)
            ->call('deletePhoto')
            ->assertSet('showPhotoDeleteModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('customer_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing('customer-photos/contoh.jpg');
    }

    public function test_ringkasan_dan_riwayat_pelanggan(): void
    {
        $customer = Customer::factory()->create();
        $typeKabel = DamageType::factory()->create(['name' => 'Kabel Putus']);
        $typeRouter = DamageType::factory()->create(['name' => 'Router Rusak']);

        // 2 komplain kabel + 1 router → tersering = Kabel Putus
        DamageReport::factory()->count(2)->create([
            'customer_id'    => $customer->id,
            'damage_type_id' => $typeKabel->id,
        ]);
        DamageReport::factory()->create([
            'customer_id'    => $customer->id,
            'damage_type_id' => $typeRouter->id,
        ]);

        $stats = Volt::test('pages.pelanggan.detail', ['customer' => $customer])->get('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(3, $stats['bulan_ini']);
        $this->assertSame('Kabel Putus', $stats['tersering']);
        $this->assertNotNull($stats['terakhir']);
    }
}
