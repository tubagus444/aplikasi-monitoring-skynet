<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimalkan query "titik terbaru per teknisi + report" yang dipanggil
     * monitoring.blade.php tiap 10 detik:
     *   LocationLog::where('technician_id', ...)->where('report_id', ...)
     *       ->latest('recorded_at')->first()
     * Index komposit ini melayani WHERE + ORDER BY sekaligus tanpa filesort.
     */
    public function up(): void
    {
        Schema::table('location_logs', function (Blueprint $table) {
            $table->index(
                ['technician_id', 'report_id', 'recorded_at'],
                'location_logs_tech_report_recorded_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('location_logs', function (Blueprint $table) {
            $table->dropIndex('location_logs_tech_report_recorded_idx');
        });
    }
};
