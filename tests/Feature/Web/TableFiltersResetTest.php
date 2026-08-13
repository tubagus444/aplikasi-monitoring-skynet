<?php

namespace Tests\Feature\Web;

use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Regresi trait App\Livewire\Concerns\WithTableFilters: mengubah `search` atau
 * properti berawalan `filter` harus mengembalikan paginasi ke halaman 1 (supaya
 * pengguna tidak "terjebak" di halaman 2 yang kosong setelah memfilter).
 *
 * Menguji kedua cabang hook updated(): properti `search` dan prefix `filter`.
 */
class TableFiltersResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_ubah_search_mereset_paginasi_ke_halaman_satu(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        DamageReport::factory()->count(15)->create(); // > 10 → ada halaman 2

        Volt::test('pages.laporan.aktif')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('search', 'apa pun')
            ->assertSet('paginators.page', 1);
    }

    public function test_ubah_filter_mereset_paginasi_ke_halaman_satu(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        DamageReport::factory()->count(15)->create();

        Volt::test('pages.laporan.aktif')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('filterStatus', 'ditugaskan')
            ->assertSet('paginators.page', 1);
    }
}
