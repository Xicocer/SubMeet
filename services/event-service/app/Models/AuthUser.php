<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'auth_user_id',
        'full_name',
        'email',
        'phone',
        'birth_date',
        'role',
        'role_id',
        'status',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'auth_user_id' => 'integer',
            'role_id' => 'integer',
            'status' => 'integer',
            'birth_date' => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
