<?php

return [
    'reservation_ttl_minutes' => (int) env('BOOKING_RESERVATION_TTL_MINUTES', 15),
    'vip_price_multiplier' => (float) env('BOOKING_VIP_PRICE_MULTIPLIER', 1.5),
    'currency' => env('BOOKING_CURRENCY', 'RUB'),
    'ticket_dispatch_driver' => env('BOOKING_TICKET_DISPATCH_DRIVER', 'rabbitmq'),
    'ticket_queue_name' => env('BOOKING_TICKET_QUEUE_NAME', 'tickets'),
];
