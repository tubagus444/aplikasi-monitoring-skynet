<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamageReport extends Model
{
    use HasFactory;
    protected $fillable = [
        'created_by',
        'damage_type_id',
        'category',
        'customer_id',
        'customer_name',
        'title',
        'address',
        'notes',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

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
                  ->orWhere('title', 'like', "%{$search}%")
            ))
            ->when($period === 'minggu', fn ($q) =>
                $q->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])
            )
            ->when($period === 'bulan', fn ($q) =>
                $q->whereMonth('completed_at', now()->month)
                  ->whereYear('completed_at', now()->year)
            )
            ->latest('completed_at');
    }

    /**
     * Headline laporan untuk tabel/PDF/Android — satu sumber tampilan tak peduli
     * kategori. Kategori `pelanggan` pakai snapshot `customer_name`; kategori
     * `jaringan`/`pemeliharaan` pakai `title`.
     */
    protected function judul(): Attribute
    {
        return Attribute::get(fn () => $this->customer_name ?? $this->title);
    }

    /**
     * Waktu selesai efektif: `completed_at` (sumber kebenaran), dengan fallback
     * ke `updated_at` untuk data lama yang belum sempat ter-backfill.
     */
    protected function waktuSelesai(): Attribute
    {
        return Attribute::get(fn () => $this->completed_at ?? $this->updated_at);
    }

    /**
     * Durasi penanganan (dibuat → selesai) dalam teks ringkas. Satu sumber untuk
     * tabel/modal riwayat & ekspor PDF. $singkat memakai "mnt" (kolom sempit),
     * selain itu "menit".
     */
    public function durasiPenanganan(bool $singkat = false): string
    {
        $d = $this->created_at->diff($this->waktu_selesai);

        return match (true) {
            $d->days > 0 => $d->days . ' hari',
            $d->h > 0    => $d->h . ' jam',
            default      => $d->i . ($singkat ? ' mnt' : ' menit'),
        };
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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

    /** Foto bukti pekerjaan teknisi (sebelum/sesudah perbaikan). */
    public function photos()
    {
        return $this->hasMany(ReportPhoto::class, 'report_id');
    }
}