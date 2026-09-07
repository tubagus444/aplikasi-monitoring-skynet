<?php

namespace Tests\Feature\Web;

use App\Enums\CustomerStatus;
use App\Enums\IpPoolStatus;
use App\Models\Customer;
use App\Models\IpPool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class IpPoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_halaman_ip_pool_dapat_diakses_oleh_admin(): void
    {
        IpPool::factory()->count(3)->create();

        $response = $this->get(route('ip-pools.index'));
        $response->assertOk();
    }

    public function test_admin_dapat_menambah_ip_satuan(): void
    {
        Volt::test('pages.ip-pool.index')
            ->set('ip_address', '192.168.10.25')
            ->set('segment', 'Cluster Cibitung')
            ->set('status', IpPoolStatus::Tersedia->value)
            ->set('notes', 'IP tes')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('ip_pools', [
            'ip_address' => '192.168.10.25',
            'segment'    => 'Cluster Cibitung',
            'status'     => IpPoolStatus::Tersedia->value,
            'notes'      => 'IP tes',
        ]);
    }

    public function test_validasi_ip_satuan_harus_format_ipv4_dan_unik(): void
    {
        IpPool::create([
            'ip_address' => '192.168.10.1',
            'status'     => IpPoolStatus::Reserved->value,
        ]);

        // IP bukan format ipv4
        Volt::test('pages.ip-pool.index')
            ->set('ip_address', 'invalid-ip')
            ->call('save')
            ->assertHasErrors(['ip_address']);

        // IP duplikat
        Volt::test('pages.ip-pool.index')
            ->set('ip_address', '192.168.10.1')
            ->call('save')
            ->assertHasErrors(['ip_address']);
    }

    public function test_admin_dapat_men_generate_rentang_ip_secara_batch(): void
    {
        Volt::test('pages.ip-pool.index')
            ->set('start_ip', '192.168.10.10')
            ->set('end_ip', '192.168.10.15')
            ->set('batch_segment', 'Subnet Tambun')
            ->set('batch_notes', 'Alokasi batch')
            ->call('generateBatch')
            ->assertHasNoErrors()
            ->assertSet('showBatchModal', false);

        // Harus ada 6 IP dari .10 sampai .15
        $this->assertSame(6, IpPool::where('segment', 'Subnet Tambun')->count());
        $this->assertDatabaseHas('ip_pools', ['ip_address' => '192.168.10.10']);
        $this->assertDatabaseHas('ip_pools', ['ip_address' => '192.168.10.15']);
    }

    public function test_validasi_rentang_ip_tidak_boleh_terbalik(): void
    {
        Volt::test('pages.ip-pool.index')
            ->set('start_ip', '192.168.10.20')
            ->set('end_ip', '192.168.10.10')
            ->call('generateBatch')
            ->assertHasErrors(['start_ip']);
    }

    public function test_alokasi_ip_pool_ke_pelanggan_dan_pelepasan_saat_pelanggan_dihapus(): void
    {
        $pool = IpPool::create([
            'ip_address' => '192.168.10.50',
            'status'     => IpPoolStatus::Tersedia->value,
        ]);

        // Tambah pelanggan dan pilih IP ini
        Volt::test('pages.pelanggan.index')
            ->set('name', 'Pak Budi IP')
            ->set('phone', '081299990000')
            ->set('address', 'Jl. Anggrek No. 10')
            ->set('ip_pool_id', $pool->id)
            ->set('status', CustomerStatus::Aktif->value)
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::where('name', 'Pak Budi IP')->first();
        $this->assertNotNull($customer);
        $this->assertSame($pool->id, $customer->ip_pool_id);
        $this->assertSame('192.168.10.50', $customer->ip_address);

        // Status IP di pool harus berubah menjadi 'terpakai'
        $pool->refresh();
        $this->assertSame(IpPoolStatus::Terpakai->value, $pool->status);
        $this->assertSame($customer->id, $pool->customer_id);

        // Hapus soft delete pelanggan -> IP harus terlepas kembali menjadi 'tersedia'
        $customer->delete();
        $pool->refresh();
        $this->assertSame(IpPoolStatus::Tersedia->value, $pool->status);
        $this->assertNull($pool->customer_id);
    }

    public function test_ip_berstatus_terpakai_tidak_dapat_dihapus_dari_pool(): void
    {
        $customer = Customer::factory()->create();
        $pool = IpPool::create([
            'ip_address'  => '192.168.10.99',
            'status'      => IpPoolStatus::Terpakai->value,
            'customer_id' => $customer->id,
        ]);

        Volt::test('pages.ip-pool.index')
            ->call('confirmDelete', $pool->id)
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseHas('ip_pools', ['id' => $pool->id]);
    }

    public function test_filter_dan_pencarian_ip_pool(): void
    {
        IpPool::create(['ip_address' => '10.0.0.1', 'segment' => 'Core', 'status' => IpPoolStatus::Reserved->value]);
        IpPool::create(['ip_address' => '192.168.1.10', 'segment' => 'Cluster A', 'status' => IpPoolStatus::Tersedia->value]);

        $comp = Volt::test('pages.ip-pool.index')
            ->set('filterStatus', IpPoolStatus::Reserved->value);

        $rows = $comp->get('ipPools');
        $this->assertCount(1, $rows);
        $this->assertSame('10.0.0.1', $rows->first()->ip_address);

        // Cari berdasarkan IP
        $comp->set('filterStatus', '')->set('search', '192.168.1.10');
        $rows = $comp->get('ipPools');
        $this->assertCount(1, $rows);
        $this->assertSame('192.168.1.10', $rows->first()->ip_address);
    }
}
