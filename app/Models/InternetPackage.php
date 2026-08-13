<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternetPackage extends Model
{
    use HasFactory;

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
}
