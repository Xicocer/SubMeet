<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyPointAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'earned_total',
        'spent_total',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'balance' => 'integer',
            'earned_total' => 'integer',
            'spent_total' => 'integer',
        ];
    }
}
