<?php

namespace App\Services;

use PhpAmqpLib\Message\AMQPMessage;

class TicketJobPublisher
{
    public function __construct(
        private readonly RabbitMqConnectionFactory $connectionFactory,
    ) {
    }

    public function publishIssueTicket(int $bookingId): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $connection = $this->connectionFactory->create();
        $channel = $connection->channel();

        try {
            $channel->exchange_declare(
                exchange: config('rabbitmq.exchange'),
                type: 'direct',
                passive: false,
                durable: true,
                auto_delete: false,
            );

            [$queueName] = $channel->queue_declare(
                queue: config('rabbitmq.ticket_jobs_queue'),
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            $channel->queue_bind(
                $queueName,
                config('rabbitmq.exchange'),
                config('rabbitmq.ticket_jobs_routing_key'),
            );

            $payload = [
                'event_type' => 'booking.ticket.issue',
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'booking_id' => $bookingId,
                ],
            ];

            $message = new AMQPMessage(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2,
                ],
            );

            $channel->basic_publish(
                msg: $message,
                exchange: config('rabbitmq.exchange'),
                routing_key: config('rabbitmq.ticket_jobs_routing_key'),
            );
        } finally {
            try {
                $channel->close();
            } catch (\Throwable) {
            }

            try {
                $connection->close();
            } catch (\Throwable) {
            }
        }
    }
}
