<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom customer_code ke tabel customers.
     * Menggunakan penomoran auto-generate unik (format: SKY-0001) untuk mencegah duplikasi data pelanggan.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_code', 20)->nullable()->after('id');
        });

        // Backfill data pelanggan yang sudah ada
        $customers = DB::table('customers')->orderBy('id')->get();
        foreach ($customers as $index => $customer) {
            $code = sprintf('SKY-%04d', $index + 1);
            DB::table('customers')->where('id', $customer->id)->update(['customer_code' => $code]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('customer_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['customer_code']);
            $table->dropColumn('customer_code');
        });
    }
};
