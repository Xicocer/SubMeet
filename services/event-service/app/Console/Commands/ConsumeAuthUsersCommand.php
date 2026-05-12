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
        $shouldStop = false;
        $maxMessages = (int) $this->option('max-messages');
        $stopAfterOne = (bool) $this->option('once');
        $idleTimeout = max(0, (int) $this->option('idle-timeout'));

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
                    &$shouldStop,
                    $maxMessages,
                    $stopAfterOne
                ) {
                    $deliveryTag = $message->delivery_info['delivery_tag'];

                    try {
                        $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        $authUser = $projector->handle($payload);
                        $processed++;

                        $this->line("Projected auth user {$authUser->auth_user_id}.");

                        try {
                            $channel->basic_ack($deliveryTag);
                        } catch (Throwable $transportException) {
                            report($transportException);
                            $shouldStop = true;
                            $this->warn('RabbitMQ connection dropped after projection processing. Stopping consumer gracefully.');

                            try {
                                $channel->basic_cancel($consumerTag);
                            } catch (Throwable) {
                            }

                            return;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error('Failed to process RabbitMQ message: ' . $exception->getMessage());

                        try {
                            $channel->basic_reject($deliveryTag, false);
                        } catch (Throwable $transportException) {
                            report($transportException);
                            $shouldStop = true;
                            $this->warn('RabbitMQ connection dropped while rejecting a failed projection message. Stopping consumer gracefully.');

                            try {
                                $channel->basic_cancel($consumerTag);
                            } catch (Throwable) {
                            }
                        }
                    }

                    if (
                        $stopAfterOne ||
                        ($maxMessages > 0 && $processed >= $maxMessages)
                    ) {
                        try {
                            $channel->basic_cancel($consumerTag);
                        } catch (Throwable $transportException) {
                            report($transportException);
                            $shouldStop = true;
                        }
                    }
                }
            );

            $this->info("Waiting for auth user events on queue [{$queueName}]...");

            while (count($channel->callbacks) > 0 && ! $shouldStop) {
                try {
                    if ($idleTimeout > 0) {
                        $channel->wait(null, false, $idleTimeout);
                    } else {
                        $channel->wait();
                    }
                } catch (AMQPTimeoutException) {
                    $this->warn("No new RabbitMQ messages received in {$idleTimeout} seconds.");
                    break;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->warn('RabbitMQ consumer stopped because the connection was interrupted.');
                    break;
                }
            }
        } finally {
            try {
                $channel->close();
            } catch (Throwable) {
            }

            try {
                $connection->close();
            } catch (Throwable) {
            }
        }

        $this->info("Processed {$processed} auth user event(s).");

        return self::SUCCESS;
    }
}
