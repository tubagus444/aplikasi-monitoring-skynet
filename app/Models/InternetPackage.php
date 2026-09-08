<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InternetPackage extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'speed_mbps',
        'price',
        'description',
    ];

    // Relasi

    /** Pelanggan yang menggunakan paket ini. */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'internet_package_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('paket-internet')
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Paket internet baru ditambahkan',
                'updated' => 'Paket internet diperbarui',
                'deleted' => 'Paket internet dihapus',
                default => "Paket internet {$eventName}",
            });
    }
}
