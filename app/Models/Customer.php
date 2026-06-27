<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'ip_address',
        'subscription_package',
        'status',
        'latitude',
        'longitude',
        'installed_at',
    ];

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
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
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
}
