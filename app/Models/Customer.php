<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_code',
        'name',
        'phone',
        'address',
        'ip_address',
        'ip_pool_id',
        'internet_package_id',
        'status',
        'latitude',
        'longitude',
        'installed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->customer_code)) {
                $customer->customer_code = static::generateNextCode();
            }
        });

        // Otomatis lepaskan IP Pool kembali menjadi 'tersedia' saat pelanggan di-soft-delete atau dihapus
        static::deleted(function (Customer $customer) {
            if ($customer->ip_pool_id) {
                $customer->ipPool?->release();
                $customer->updateQuietly([
                    'ip_pool_id' => null,
                    'ip_address' => null,
                ]);
            }
        });
    }

    /**
     * Generate kode pelanggan berikutnya berformat SKY-0001, SKY-0002, dst.
     * Mengikutsertakan pelanggan yang di-soft-delete (withTrashed) agar nomor tidak pernah terduplikasi.
     */
    public static function generateNextCode(): string
    {
        $maxCode = static::withTrashed()
            ->whereNotNull('customer_code')
            ->where('customer_code', 'like', 'SKY-%')
            ->when(
                \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql',
                fn ($q) => $q->orderByRaw('CAST(SUBSTRING(customer_code, 5) AS UNSIGNED) DESC'),
                fn ($q) => $q->orderByRaw('CAST(SUBSTR(customer_code, 5) AS INTEGER) DESC')
            )
            ->value('customer_code');

        $maxNumber = 0;
        if ($maxCode && preg_match('/^SKY-(\d+)$/', $maxCode, $matches)) {
            $maxNumber = (int) $matches[1];
        }

        return sprintf('SKY-%04d', $maxNumber + 1);
    }

    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'latitude'     => 'decimal:8',
            'longitude'    => 'decimal:8',
        ];
    }

    /**
     * Filter daftar pelanggan (search + status), terurut nama. Sumber kebenaran
     * tunggal yang dipakai bersama halaman Pelanggan (Volt), ekspor PDF, & ekspor
     * Excel — agar ketiganya selalu memakai kueri yang sama.
     */
    public function scopeFiltered(Builder $query, ?string $search = null, ?string $status = null): Builder
    {
        return $query
            ->when($search, fn ($q) => $q->where(fn ($w) =>
                $w->where('customer_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('internetPackage', fn ($ip) =>
                      $ip->where('name', 'like', "%{$search}%")
                  )
            ))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name');
    }

    // Relasi

    /**
     * Riwayat laporan pelanggan ini (semua status). Dasar fitur "riwayat perbaikan
     * per pelanggan" di detail pelanggan — konsekuensi gratis dari relasi ini.
     */
    public function reports()
    {
        return $this->hasMany(DamageReport::class, 'customer_id');
    }

    /** Foto rumah pelanggan (wayfinding). */
    public function photos()
    {
        return $this->hasMany(CustomerPhoto::class, 'customer_id');
    }

    /** Paket internet pelanggan (master data). */
    public function internetPackage()
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }

    /** Master data IP Pool yang dialokasikan untuk pelanggan ini. */
    public function ipPool()
    {
        return $this->belongsTo(IpPool::class, 'ip_pool_id');
    }
}
