<?php

namespace App\Services;

use App\Models\AuthUser;
use InvalidArgumentException;

class AuthUserProjector
{
    public function handle(array $message): AuthUser
    {
        $eventType = $message['event_type'] ?? null;

        if (!in_array($eventType, ['auth.user.created', 'auth.user.updated', 'auth.user.synced'], true)) {
            throw new InvalidArgumentException('Unsupported auth user event type.');
        }

        $payload = $message['payload'] ?? null;

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Auth user event payload must be an array.');
        }

        return AuthUser::query()->updateOrCreate(
            [
                'auth_user_id' => $payload['id'],
            ],
            [
                'full_name' => $payload['full_name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'birth_date' => $payload['birth_date'] ?? null,
                'role' => data_get($payload, 'role.role'),
                'role_id' => data_get($payload, 'role.id'),
                'status' => $payload['status'] ?? 1,
                'synced_at' => now(),
            ]
        );
    }
}
