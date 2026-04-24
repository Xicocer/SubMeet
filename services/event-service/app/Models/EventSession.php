<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EventSession extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'event_id',
        'hall_id',
        'start_time',
        'end_time',
        'base_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'hall_id' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'base_price' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_SCHEDULED)
            ->where('start_time', '>=', now());
    }
}
