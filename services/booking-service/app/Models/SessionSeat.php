<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionSeat extends Model
{
    use HasFactory;

    public const STATUS_FREE = 'free';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_BOOKED = 'booked';

    protected $fillable = [
        'session_snapshot_id',
        'element_id',
        'type',
        'label',
        'level_id',
        'row_label',
        'seat_number',
        'price',
        'status',
        'booking_id',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'session_snapshot_id' => 'integer',
            'price' => 'decimal:2',
            'booking_id' => 'integer',
            'reserved_until' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SessionSnapshot::class, 'session_snapshot_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
