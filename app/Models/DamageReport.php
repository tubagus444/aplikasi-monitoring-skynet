<?php

namespace App\Models;

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