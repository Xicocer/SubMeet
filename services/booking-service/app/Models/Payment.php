<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'booking_id',
        'provider',
        'status',
        'amount',
        'currency',
        'external_reference',
        'confirmation_url',
        'failure_reason',
        'payload',
        'paid_at',
        'cancelled_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
