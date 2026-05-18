<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallRentalRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hall_id',
        'organizer_id',
        'event_id',
        'requested_start',
        'requested_end',
        'hourly_rate',
        'total_amount',
        'status',
        'organizer_message',
        'response_note',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'hall_id' => 'integer',
            'organizer_id' => 'integer',
            'event_id' => 'integer',
            'requested_start' => 'datetime',
            'requested_end' => 'datetime',
            'hourly_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function durationMinutes(): float
    {
        if ($this->requested_start === null || $this->requested_end === null) {
            return 0.0;
        }

        return max(1.0, (float) $this->requested_start->diffInMinutes($this->requested_end, true));
    }

    public function calculatedTotalAmount(): float
    {
        return round((float) $this->hourly_rate * ($this->durationMinutes() / 60), 2);
    }
}
