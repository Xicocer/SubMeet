<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 120.0),
    'channel_rpc_timeout' => (float) env('RABBITMQ_CHANNEL_RPC_TIMEOUT', 120.0),
    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 60),
    'keepalive' => filter_var(env('RABBITMQ_KEEPALIVE', true), FILTER_VALIDATE_BOOL),
    'exchange' => env('RABBITMQ_EXCHANGE', 'submeet.domain'),
    'auth_user_projection_queue' => env('RABBITMQ_AUTH_USERS_QUEUE', 'event-service.auth-users'),
];
