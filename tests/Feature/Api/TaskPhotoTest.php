<?php

namespace Tests\Feature\Api;

use App\Enums\ReportCategory;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_teknisi_dapat_unggah_foto_bukti_pekerjaan(): void
    {
        Storage::fake('public');
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $response = $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/photos", [
                'photo'   => UploadedFile::fake()->image('bukti.jpg'),
                'caption' => 'Kondisi sesudah',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.caption', 'Kondisi sesudah')
            ->assertJsonStructure(['data' => ['id', 'url', 'caption']]);

        $this->assertDatabaseHas('report_photos', [
            'report_id'   => $report->id,
            'uploaded_by' => $teknisi->id,
            'caption'     => 'Kondisi sesudah',
        ]);

        $path = $report->photos()->first()->path;
        Storage::disk('public')->assertExists($path);
    }

    public function test_foto_bukti_wajib_berupa_gambar(): void
    {
        Storage::fake('public');
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/photos", [
                'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable();
    }

    public function test_teknisi_tidak_dapat_unggah_foto_ke_tugas_orang_lain(): void
    {
        Storage::fake('public');
        $teknisi    = User::factory()->teknisi()->create();
        $lain       = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $lain->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/photos", [
                'photo' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('report_photos', 0);
    }

    public function test_foto_bukti_muncul_di_detail_tugas(): void
    {
        Storage::fake('public');
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->sedangDiperbaiki()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/photos", [
                'photo'   => UploadedFile::fake()->image('bukti.jpg'),
                'caption' => 'Konektor diganti',
            ])->assertCreated();

        $this->actingAs($teknisi, 'sanctum')
            ->getJson("/api/tasks/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.repair_photos.0.caption', 'Konektor diganti')
            ->assertJsonPath('data.repair_photos.0.technician', $teknisi->name)
            ->assertJsonStructure(['data' => ['repair_photos' => [['url', 'caption', 'technician', 'uploaded_at']]]]);
    }

    public function test_teknisi_dapat_unggah_foto_rumah_untuk_laporan_pelanggan(): void
    {
        Storage::fake('public');
        $teknisi    = User::factory()->teknisi()->create();
        $customer   = Customer::factory()->create();
        $report     = DamageReport::factory()->create([
            'category'    => ReportCategory::Pelanggan->value,
            'customer_id' => $customer->id,
        ]);
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $response = $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/house-photos", [
                'photo'   => UploadedFile::fake()->image('rumah.jpg'),
                'caption' => 'Tampak depan',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.caption', 'Tampak depan');

        $this->assertDatabaseHas('customer_photos', [
            'customer_id' => $customer->id,
            'uploaded_by' => $teknisi->id,
            'caption'     => 'Tampak depan',
        ]);
    }

    public function test_unggah_foto_rumah_ditolak_untuk_laporan_non_pelanggan(): void
    {
        Storage::fake('public');
        $teknisi    = User::factory()->teknisi()->create();
        $report     = DamageReport::factory()->jaringan()->create();
        $assignment = TaskAssignment::create(['report_id' => $report->id, 'technician_id' => $teknisi->id]);

        $this->actingAs($teknisi, 'sanctum')
            ->postJson("/api/tasks/{$assignment->id}/house-photos", [
                'photo' => UploadedFile::fake()->image('rumah.jpg'),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('customer_photos', 0);
    }
}
