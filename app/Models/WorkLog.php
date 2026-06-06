<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'technician_id',
        'status',
        'description',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
        ];
    }

    // Relasi
    public function report()
    {
        return $this->belongsTo(DamageReport::class, 'report_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}