<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgeRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'min_age',
    ];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
