<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqConnectionFactory
{
    public function create(): AMQPStreamConnection
    {
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        try {
            return new AMQPStreamConnection(
                host: config('rabbitmq.host'),
                port: (int) config('rabbitmq.port'),
                user: config('rabbitmq.user'),
                password: config('rabbitmq.password'),
                vhost: config('rabbitmq.vhost'),
            );
        } finally {
            error_reporting($previousErrorReporting);
        }
    }
}
