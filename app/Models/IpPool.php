<?php

namespace App\Models;

use App\Enums\IpPoolStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class IpPool extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'ip_address',
        'segment',
        'status',
        'customer_id',
        'notes',
    ];

    /**
     * Pelanggan yang saat ini sedang menggunakan IP ini (jika status terpakai).
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Scope: Hanya IP yang berstatus tersedia (siap dialokasikan).
     */
    public function scopeTersedia(Builder $query): Builder
    {
        return $query->where('status', IpPoolStatus::Tersedia->value);
    }

    /**
     * Scope pencarian & filter tabel IP Pool.
     */
    public function scopeFiltered(
        Builder $query,
        ?string $search = null,
        ?string $status = null,
        ?string $segment = null,
    ): Builder {
        return $query
            ->when($search, fn ($q) => $q->where(fn ($w) =>
                $w->where('ip_address', 'like', "%{$search}%")
                  ->orWhere('segment', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) =>
                      $c->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                  )
            ))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($segment, fn ($q) => $q->where('segment', $segment))
            ->when(
                \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql',
                fn ($q) => $q->orderByRaw('INET_ATON(ip_address) ASC'),
                fn ($q) => $q->orderBy('ip_address', 'asc')
            );
    }

    /**
     * Alokasikan IP ini ke pelanggan tertentu.
     */
    public function allocateTo(Customer $customer): void
    {
        $this->update([
            'status'      => IpPoolStatus::Terpakai->value,
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Lepaskan IP ini kembali ke status 'tersedia'.
     */
    public function release(): void
    {
        $this->update([
            'status'      => IpPoolStatus::Tersedia->value,
            'customer_id' => null,
        ]);
    }

    /**
     * Tandai IP sebagai dicadangkan (reserved) untuk gateway/infrastruktur.
     */
    public function markAsReserved(?string $notes = null): void
    {
        $this->update([
            'status'      => IpPoolStatus::Reserved->value,
            'customer_id' => null,
            'notes'       => $notes ?? $this->notes,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ip-pool')
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'IP Pool ditambahkan',
                'updated' => 'IP Pool diperbarui',
                'deleted' => 'IP Pool dihapus',
                default => "IP Pool {$eventName}",
            });
    }
}
