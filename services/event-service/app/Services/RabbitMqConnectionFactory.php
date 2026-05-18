<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqConnectionFactory
{
    public function create(): AMQPStreamConnection
    {
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        $keepalive = (bool) config('rabbitmq.keepalive', true)
            && function_exists('socket_import_stream')
            && defined('SOL_SOCKET')
            && defined('SO_KEEPALIVE');

        try {
            return new AMQPStreamConnection(
                host: config('rabbitmq.host'),
                port: (int) config('rabbitmq.port'),
                user: config('rabbitmq.user'),
                password: config('rabbitmq.password'),
                vhost: config('rabbitmq.vhost'),
                connection_timeout: (float) config('rabbitmq.connection_timeout', 3.0),
                read_write_timeout: (float) config('rabbitmq.read_write_timeout', 120.0),
                context: null,
                keepalive: $keepalive,
                heartbeat: (int) config('rabbitmq.heartbeat', 60),
            );
        } finally {
            error_reporting($previousErrorReporting);
        }
    }
}
