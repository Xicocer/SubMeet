<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    use HasFactory;

    public const TYPE_SEAT = 'seat';
    public const TYPE_STANDING = 'standing';

    protected $fillable = [
        'booking_id',
        'item_type',
        'session_seat_id',
        'session_standing_area_id',
        'external_element_id',
        'label',
        'quantity',
        'unit_price',
        'total_price',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'session_seat_id' => 'integer',
            'session_standing_area_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(SessionSeat::class, 'session_seat_id');
    }

    public function standingArea(): BelongsTo
    {
        return $this->belongsTo(SessionStandingArea::class, 'session_standing_area_id');
    }
}
