<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
