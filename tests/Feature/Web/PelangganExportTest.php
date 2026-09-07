<?php

namespace Tests\Feature\Web;

use App\Enums\CustomerStatus;
use App\Exports\CustomersExport;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Ekspor daftar pelanggan ke PDF & Excel — harus mengikuti filter (search + status)
 * yang aktif, lewat scope tunggal Customer::filtered (sama dengan halaman).
 */
class PelangganExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_ekspor_pdf_pelanggan(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->get(route('customers.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_ekspor_excel_pelanggan(): void
    {
        Excel::fake();
        Customer::factory()->count(3)->create();

        $this->get(route('customers.export.excel'))->assertOk();

        Excel::assertDownloaded('pelanggan.xlsx');
    }

    public function test_ekspor_excel_mengikuti_filter_status(): void
    {
        Excel::fake();
        Customer::factory()->count(2)->create(); // aktif (default)
        Customer::factory()->isolir()->create(['name' => 'Pelanggan Isolir']);

        $this->get(route('customers.export.excel', ['status' => CustomerStatus::Isolir->value]))->assertOk();

        Excel::assertDownloaded('pelanggan.xlsx', function (CustomersExport $export) {
            $rows = $export->query()->get();

            return $rows->count() === 1
                && $rows->every(fn ($c) => $c->status === CustomerStatus::Isolir->value);
        });
    }

    public function test_scope_filtered_dipakai_bersama_search_dan_status(): void
    {
        // Scope tunggal yang dipakai halaman + PDF + Excel.
        Customer::factory()->create(['name' => 'Pak Hendra Unik']);            // aktif
        Customer::factory()->isolir()->create(['name' => 'Bu Sari Isolir']);

        $this->assertSame(1, Customer::filtered('Hendra', null)->count());
        $this->assertSame('Pak Hendra Unik', Customer::filtered('Hendra', null)->first()->name);

        $isolir = Customer::filtered(null, CustomerStatus::Isolir->value)->get();
        $this->assertCount(1, $isolir);
        $this->assertSame('Bu Sari Isolir', $isolir->first()->name);
    }

    public function test_excel_export_menyertakan_customer_code(): void
    {
        $customer = Customer::factory()->create(['name' => 'Testing Export']);
        $export = new CustomersExport();

        $this->assertContains('Kode Pelanggan', $export->headings());
        $mapped = $export->map($customer);
        $this->assertSame($customer->customer_code, $mapped[0]);
    }
}
