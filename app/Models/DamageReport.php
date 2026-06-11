<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamageReport extends Model
{
    use HasFactory;
    protected $fillable = [
        'created_by',
        'damage_type_id',
        'customer_name',
        'address',
        'notes',
        'status',
    ];

    /**
     * Riwayat laporan yang sudah selesai, dengan filter pencarian & periode.
     * Dipakai bersama oleh halaman Riwayat (Volt) dan ekspor PDF agar konsisten.
     */
    public function scopeRiwayatSelesai(Builder $query, ?string $search = null, ?string $period = null): Builder
    {
        return $query->where('status', ReportStatus::Selesai->value)
            ->when($search, fn ($q) => $q->where(fn ($w) =>
                $w->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
            ))
            ->when($period === 'minggu', fn ($q) =>
                $q->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])
            )
            ->when($period === 'bulan', fn ($q) =>
                $q->whereMonth('updated_at', now()->month)
                  ->whereYear('updated_at', now()->year)
            )
            ->latest('updated_at');
    }

    // Relasi
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function damageType()
    {
        return $this->belongsTo(DamageType::class, 'damage_type_id');
    }

    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'report_id');
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class, 'report_id');
    }

    public function locationLogs()
    {
        return $this->hasMany(LocationLog::class, 'report_id');
    }
}