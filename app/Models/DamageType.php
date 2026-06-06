<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    // Relasi
    public function damageReports()
    {
        return $this->hasMany(DamageReport::class, 'damage_type_id');
    }
}