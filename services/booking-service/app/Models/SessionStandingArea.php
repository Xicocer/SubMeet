<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionStandingArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_snapshot_id',
        'element_id',
        'label',
        'level_id',
        'price',
        'capacity_total',
        'capacity_available',
    ];

    protected function casts(): array
    {
        return [
            'session_snapshot_id' => 'integer',
            'price' => 'decimal:2',
            'capacity_total' => 'integer',
            'capacity_available' => 'integer',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SessionSnapshot::class, 'session_snapshot_id');
    }
}
