<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel master ip_pools, menambahkan FK ip_pool_id pada customers,
     * dan snapshot customer_ip pada damage_reports.
     */
    public function up(): void
    {
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('segment', 50)->nullable();
            $table->string('status', 20)->default('tersedia');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('ip_pool_id')->nullable()->after('ip_address')->constrained('ip_pools')->nullOnDelete();
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->string('customer_ip', 45)->nullable()->after('customer_name');
        });

        // Backfill data pelanggan yang saat ini sudah memiliki ip_address ke dalam ip_pools
        $existingCustomers = DB::table('customers')
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->get();

        foreach ($existingCustomers as $c) {
            $existingPool = DB::table('ip_pools')->where('ip_address', $c->ip_address)->first();
            if ($existingPool) {
                $poolId = $existingPool->id;
            } else {
                $poolId = DB::table('ip_pools')->insertGetId([
                    'ip_address'  => $c->ip_address,
                    'segment'     => 'Default',
                    'status'      => 'terpakai',
                    'customer_id' => $c->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            DB::table('customers')->where('id', $c->id)->update(['ip_pool_id' => $poolId]);
        }

        // Backfill snapshot customer_ip pada laporan gangguan yang sudah ada
        $existingReports = DB::table('damage_reports')->whereNotNull('customer_id')->get();
        foreach ($existingReports as $r) {
            $cust = DB::table('customers')->where('id', $r->customer_id)->first();
            if ($cust && $cust->ip_address) {
                DB::table('damage_reports')->where('id', $r->id)->update(['customer_ip' => $cust->ip_address]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropColumn('customer_ip');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ip_pool_id');
        });

        Schema::dropIfExists('ip_pools');
    }
};
