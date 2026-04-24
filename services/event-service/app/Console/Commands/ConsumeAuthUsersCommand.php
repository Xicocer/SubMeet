<?php

namespace App\Console\Commands;

use App\Services\AuthUserProjector;
use App\Services\RabbitMqConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumeAuthUsersCommand extends Command
{
    protected $signature = 'rabbitmq:consume-auth-users
        {--once : Stop after processing the first message}
        {--max-messages=0 : Stop after processing N messages}
        {--idle-timeout=5 : Stop if no message is received within N seconds}';

    protected $description = 'Consume auth user events from RabbitMQ and update local projections';

    public function handle(
        RabbitMqConnectionFactory $connectionFactory,
        AuthUserProjector $projector,
    ): int {
        $connection = $connectionFactory->create();
        $channel = $connection->channel();
        $consumerTag = 'event-service-auth-users-consumer';
        $processed = 0;
        $maxMessages = (int) $this->option('max-messages');
        $stopAfterOne = (bool) $this->option('once');
        $idleTimeout = (int) $this->option('idle-timeout');

        try {
            $channel->exchange_declare(
                exchange: config('rabbitmq.exchange'),
                type: 'topic',
                passive: false,
                durable: true,
                auto_delete: false,
            );

            [$queueName] = $channel->queue_declare(
                queue: config('rabbitmq.auth_user_projection_queue'),
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            foreach (['auth.user.created', 'auth.user.updated', 'auth.user.synced'] as $routingKey) {
                $channel->queue_bind($queueName, config('rabbitmq.exchange'), $routingKey);
            }

            $channel->basic_qos(null, 1, null);

            $channel->basic_consume(
                queue: $queueName,
                consumer_tag: $consumerTag,
                no_local: false,
                no_ack: false,
                exclusive: false,
                nowait: false,
                callback: function (AMQPMessage $message) use (
                    $projector,
                    $channel,
                    $consumerTag,
                    &$processed,
                    $maxMessages,
                    $stopAfterOne
                ) {
                    try {
                        $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        $authUser = $projector->handle($payload);
                        $channel->basic_ack($message->delivery_info['delivery_tag']);
                        $processed++;

                        $this->line("Projected auth user {$authUser->auth_user_id}.");
                    } catch (Throwable $exception) {
                        $channel->basic_reject($message->delivery_info['delivery_tag'], false);
                        $this->error('Failed to process RabbitMQ message: ' . $exception->getMessage());
                    }

                    if (
                        $stopAfterOne ||
                        ($maxMessages > 0 && $processed >= $maxMessages)
                    ) {
                        $channel->basic_cancel($consumerTag);
                    }
                }
            );

            $this->info("Waiting for auth user events on queue [{$queueName}]...");

            while (count($channel->callbacks) > 0) {
                try {
                    $channel->wait(null, false, $idleTimeout);
                } catch (AMQPTimeoutException) {
                    $this->warn("No new RabbitMQ messages received in {$idleTimeout} seconds.");
                    break;
                }
            }
        } finally {
            $channel->close();
            $connection->close();
        }

        $this->info("Processed {$processed} auth user event(s).");

        return self::SUCCESS;
    }
}
