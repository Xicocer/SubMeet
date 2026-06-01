<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hall extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'venue_owner_id',
        'name',
        'address',
        'description',
        'photo_urls',
        'hourly_rate',
        'layout',
        'seat_capacity',
        'vip_capacity',
        'dancefloor_capacity',
        'total_capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'venue_owner_id' => 'integer',
            'photo_urls' => 'array',
            'hourly_rate' => 'decimal:2',
            'layout' => 'array',
            'seat_capacity' => 'integer',
            'vip_capacity' => 'integer',
            'dancefloor_capacity' => 'integer',
            'total_capacity' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(HallRentalRequest::class);
    }

    public function unavailablePeriods(): HasMany
    {
        return $this->hasMany(HallUnavailablePeriod::class);
    }
}
