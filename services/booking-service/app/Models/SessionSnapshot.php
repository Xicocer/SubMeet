<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_session_id',
        'event_id',
        'organizer_id',
        'hall_id',
        'event_title',
        'event_category_name',
        'event_category_slug',
        'event_age_rating_label',
        'event_min_age',
        'hall_name',
        'hall_address',
        'hall_layout',
        'base_price',
        'currency',
        'starts_at',
        'ends_at',
        'status',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'event_session_id' => 'integer',
            'event_id' => 'integer',
            'organizer_id' => 'integer',
            'hall_id' => 'integer',
            'event_min_age' => 'integer',
            'hall_layout' => 'array',
            'base_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SessionSeat::class);
    }

    public function standingAreas(): HasMany
    {
        return $this->hasMany(SessionStandingArea::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
