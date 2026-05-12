<?php

namespace App\Console\Commands;

use App\Jobs\IssueTicketDocumentJob;
use App\Services\RabbitMqConnectionFactory;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumeTicketJobsCommand extends Command
{
    protected $signature = 'rabbitmq:consume-ticket-jobs
        {--once : Stop after processing the first message}
        {--max-messages=0 : Stop after processing N messages}
        {--idle-timeout=0 : Stop if no message is received within N seconds. Use 0 to wait forever.}';

    protected $description = 'Consume RabbitMQ ticket generation jobs';

    public function handle(
        RabbitMqConnectionFactory $connectionFactory,
    ): int {
        $connection = $connectionFactory->create();
        $channel = $connection->channel();
        $consumerTag = 'booking-service-ticket-jobs-consumer';
        $processed = 0;
        $shouldStop = false;
        $maxMessages = (int) $this->option('max-messages');
        $stopAfterOne = (bool) $this->option('once');
        $idleTimeout = max(0, (int) $this->option('idle-timeout'));

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

            $channel->basic_qos(null, 1, null);

            $channel->basic_consume(
                queue: $queueName,
                consumer_tag: $consumerTag,
                no_local: false,
                no_ack: false,
                exclusive: false,
                nowait: false,
                callback: function (AMQPMessage $message) use (
                    $channel,
                    $consumerTag,
                    &$processed,
                    &$shouldStop,
                    $maxMessages,
                    $stopAfterOne
                ): void {
                    $deliveryTag = $message->delivery_info['delivery_tag'];

                    try {
                        $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        $bookingId = (int) data_get($payload, 'payload.booking_id', 0);

                        if ($bookingId < 1) {
                            throw new \RuntimeException('booking_id is missing in ticket job payload.');
                        }

                        IssueTicketDocumentJob::dispatchSync($bookingId);
                        $processed++;

                        $this->line("Issued ticket document for booking {$bookingId}.");

                        try {
                            $channel->basic_ack($deliveryTag);
                        } catch (Throwable $transportException) {
                            report($transportException);
                            $shouldStop = true;
                            $this->warn('RabbitMQ connection dropped after ticket processing. Stopping ticket worker gracefully.');

                            try {
                                $channel->basic_cancel($consumerTag);
                            } catch (Throwable) {
                            }

                            return;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error('Failed to process ticket job: ' . $exception->getMessage());

                        try {
                            $channel->basic_reject($deliveryTag, false);
                        } catch (Throwable $transportException) {
                            report($transportException);
                            $shouldStop = true;
                            $this->warn('RabbitMQ connection dropped while rejecting a failed ticket job. Stopping ticket worker gracefully.');

                            try {
                                $channel->basic_cancel($consumerTag);
                            } catch (Throwable) {
                            }
                        }
                    }

                    if ($stopAfterOne || ($maxMessages > 0 && $processed >= $maxMessages)) {
                        try {
                            $channel->basic_cancel($consumerTag);
                        } catch (Throwable $transportException) {
                            report($transportException);
                            $shouldStop = true;
                        }
                    }
                }
            );

            $this->info("Waiting for ticket jobs on queue [{$queueName}]...");

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

        $this->info("Processed {$processed} ticket job(s).");

        return self::SUCCESS;
    }
}
