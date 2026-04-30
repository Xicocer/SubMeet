<?php

return [
    'reservation_ttl_minutes' => (int) env('BOOKING_RESERVATION_TTL_MINUTES', 15),
    'vip_price_multiplier' => (float) env('BOOKING_VIP_PRICE_MULTIPLIER', 1.5),
    'currency' => env('BOOKING_CURRENCY', 'RUB'),
];
