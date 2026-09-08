<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Relasi
    public function damageReports()
    {
        return $this->hasMany(DamageReport::class, 'created_by');
    }

    public function taskAssignments()
    {
        return $this->hasMany(TaskAssignment::class, 'technician_id');
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class, 'technician_id');
    }

    public function locationLogs()
    {
        return $this->hasMany(LocationLog::class, 'technician_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    // Helper
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin->value;
    }

    public function isTeknisi(): bool
    {
        return $this->role === UserRole::Teknisi->value;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', 'remember_token', 'fcm_token'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('pengguna')
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Pengguna baru ditambahkan',
                'updated' => 'Data pengguna diperbarui',
                'deleted' => 'Pengguna dihapus',
                default => "Pengguna {$eventName}",
            });
    }
}