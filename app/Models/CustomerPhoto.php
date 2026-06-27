<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto rumah pelanggan (wayfinding). Append-only: hanya `created_at`, tanpa
 * `updated_at` (ikut pola tabel log). Menyimpan path file di disk `public`.
 */
class CustomerPhoto extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
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
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
