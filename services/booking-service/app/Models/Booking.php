<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    public const FLOW_RESERVATION = 'reservation';
    public const FLOW_PURCHASE = 'purchase';

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'session_snapshot_id',
        'status',
        'flow_type',
        'total_amount',
        'currency',
        'ticket_code',
        'ticket_pdf_path',
        'reserved_until',
        'confirmed_at',
        'ticket_issued_at',
        'ticket_used_at',
        'ticket_used_by_organizer_id',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'session_snapshot_id' => 'integer',
            'total_amount' => 'decimal:2',
            'reserved_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'ticket_issued_at' => 'datetime',
            'ticket_used_at' => 'datetime',
            'ticket_used_by_organizer_id' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SessionSnapshot::class, 'session_snapshot_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function scopeAwaitingCheckout(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_RESERVED,
            self::STATUS_PAYMENT_PENDING,
        ]);
    }
}
