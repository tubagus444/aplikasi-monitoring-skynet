<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto bukti pekerjaan teknisi (sebelum/sesudah perbaikan). Append-only: hanya
 * `created_at`, tanpa `updated_at` (ikut pola tabel log). Menyimpan path file di
 * disk `public`. Pasangan dari {@see CustomerPhoto} (foto rumah/wayfinding).
 */
class ReportPhoto extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'uploaded_by',
        'path',
        'caption',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // Relasi
    public function report()
    {
        return $this->belongsTo(DamageReport::class, 'report_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
