<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'related_id',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Laporan terkait (via related_id). Nullable — notifikasi lama
     * atau tipe tanpa referensi laporan tidak punya relasi ini.
     * Juga nullable bila laporan sudah dihapus (bukan FK constrained).
     */
    public function report()
    {
        return $this->belongsTo(DamageReport::class, 'related_id');
    }
}