<?php

namespace Tests\Feature\Web;

use App\Actions\SyncReportTechnicians;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_activity_log_dapat_dirender_oleh_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/activity-logs');

        $response->assertOk()
            ->assertSee('Audit Trail / Log Aktivitas');
    }

    public function test_perubahan_data_pelanggan_tercatat_di_activity_log(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $customer = Customer::create([
            'name'    => 'Pelanggan Uji',
            'phone'   => '08123456789',
            'address' => 'Alamat Awal',
            'status'  => 'aktif',
        ]);

        $customer->update(['address' => 'Alamat Baru']);

        $this->assertDatabaseHas('activity_log', [
            'log_name'     => 'pelanggan',
            'description'  => 'Data pelanggan diperbarui',
            'causer_id'    => $admin->id,
            'subject_id'   => $customer->id,
            'subject_type' => Customer::class,
        ]);

        $latestLog = Activity::where('log_name', 'pelanggan')->latest('id')->first();
        $this->assertEquals('Alamat Awal', $latestLog->properties['old']['address']);
        $this->assertEquals('Alamat Baru', $latestLog->properties['attributes']['address']);
    }

    public function test_penugasan_teknisi_tercatat_di_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $teknisi = User::factory()->teknisi()->create(['name' => 'Teknisi Handal']);

        $this->actingAs($admin);

        $report = DamageReport::create([
            'created_by' => $admin->id,
            'category'   => ReportCategory::Jaringan->value,
            'title'      => 'Kabel Putus',
            'address'    => 'Jl. Testing No. 1',
            'status'     => ReportStatus::Ditugaskan->value,
        ]);

        (new SyncReportTechnicians)($report, [$teknisi->id]);

        $this->assertDatabaseHas('activity_log', [
            'log_name'     => 'laporan',
            'description'  => 'Menugaskan teknisi: Teknisi Handal',
            'causer_id'    => $admin->id,
            'subject_id'   => $report->id,
            'subject_type' => DamageReport::class,
        ]);
    }

    public function test_filter_dan_pencarian_komponen_volt_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        activity('pelanggan')->causedBy($admin)->log('Log Pelanggan Khusus');
        activity('laporan')->causedBy($admin)->log('Log Laporan Khusus');

        Volt::test('pages.log-aktivitas.index')
            ->assertSee('Log Pelanggan Khusus')
            ->assertSee('Log Laporan Khusus')
            ->set('filterLogName', 'pelanggan')
            ->assertSee('Log Pelanggan Khusus')
            ->assertDontSee('Log Laporan Khusus');
    }

    public function test_filter_rentang_tanggal_kustom_dan_reset(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Buat log dengan tanggal lampau dan tanggal hari ini
        activity('laporan')->causedBy($admin)->createdAt(now()->subMonths(2))->log('Log Dua Bulan Lalu');
        activity('laporan')->causedBy($admin)->createdAt(now())->log('Log Hari Ini');

        Volt::test('pages.log-aktivitas.index')
            ->assertSee('Log Dua Bulan Lalu')
            ->assertSee('Log Hari Ini')
            ->set('filterPeriod', '30days')
            ->assertDontSee('Log Dua Bulan Lalu')
            ->assertSee('Log Hari Ini')
            ->set('filterPeriod', 'custom')
            ->set('filterStartDate', now()->subMonths(3)->format('Y-m-d'))
            ->set('filterEndDate', now()->subMonths(1)->format('Y-m-d'))
            ->assertSee('Log Dua Bulan Lalu')
            ->assertDontSee('Log Hari Ini')
            ->call('resetFilters')
            ->assertSee('Log Dua Bulan Lalu')
            ->assertSee('Log Hari Ini');
    }
}
