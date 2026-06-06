<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'technician_id',
        'report_id',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude'    => 'float',
            'longitude'   => 'float',
        ];
    }

    // Relasi
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function report()
    {
        return $this->belongsTo(DamageReport::class, 'report_id');
    }
}