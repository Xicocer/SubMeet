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
        'organizer_id',
        'name',
        'address',
        'description',
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
            'organizer_id' => 'integer',
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
