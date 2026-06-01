<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallUnavailablePeriod extends Model
{
    protected $fillable = [
        'hall_id',
        'unavailable_start',
        'unavailable_end',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'hall_id' => 'integer',
            'unavailable_start' => 'datetime',
            'unavailable_end' => 'datetime',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }
}
