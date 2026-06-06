<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('location_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('report_id')->constrained('damage_reports')->cascadeOnDelete();
        $table->decimal('latitude', 10, 8);
        $table->decimal('longitude', 11, 8);
        $table->timestamp('recorded_at')->useCurrent();
    });
}
};
