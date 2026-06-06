<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    protected $with = ['user'];

    protected $fillable = [
        'user_id',
        'title',
        'body',
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
}