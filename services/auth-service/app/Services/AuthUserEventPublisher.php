<?php

namespace App\Services;

use App\Models\User;
use PhpAmqpLib\Message\AMQPMessage;

class AuthUserEventPublisher
{
    public function __construct(
        private readonly RabbitMqConnectionFactory $connectionFactory,
    ) {
    }

    public function publishCreated(User $user): void
    {
        $this->publish('auth.user.created', $user);
    }

    public function publishUpdated(User $user): void
    {
        $this->publish('auth.user.updated', $user);
    }

    public function publishSynced(User $user): void
    {
        $this->publish('auth.user.synced', $user);
    }

    private function publish(string $routingKey, User $user): void
    {
        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();

        try {
            $channel->exchange_declare(
                exchange: config('rabbitmq.exchange'),
                type: 'topic',
                passive: false,
                durable: true,
                auto_delete: false,
            );

            $user->loadMissing('role');

            $payload = [
                'event_type' => $routingKey,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'birth_date' => $user->birth_date?->toDateString(),
                    'status' => $user->status,
                    'role' => [
                        'id' => $user->role?->id,
                        'role' => $user->role?->role,
                    ],
                ],
            ];

            $message = new AMQPMessage(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2,
                ]
            );

            $channel->basic_publish(
                msg: $message,
                exchange: config('rabbitmq.exchange'),
                routing_key: $routingKey,
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
