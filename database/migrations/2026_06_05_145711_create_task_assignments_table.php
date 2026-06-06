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
    Schema::create('task_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('report_id')->constrained('damage_reports')->cascadeOnDelete();
        $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
        $table->timestamp('assigned_at')->useCurrent();
    });
}
};
