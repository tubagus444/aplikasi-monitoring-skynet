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
    Schema::create('damage_reports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
        $table->foreignId('damage_type_id')->constrained('damage_types')->cascadeOnDelete();
        $table->string('customer_name');
        $table->string('address');
        $table->text('notes')->nullable();
        $table->enum('status', ['ditugaskan', 'sedang_memperbaiki', 'selesai'])
              ->default('ditugaskan');
        $table->timestamps();
    });
}
};
