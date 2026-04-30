<?php

return [
    'provider' => env('BOOKING_PAYMENT_PROVIDER', 'mock'),

    'return_url' => env('BOOKING_PAYMENT_RETURN_URL', 'http://127.0.0.1:5173/profile'),

    'checkout_url' => env('BOOKING_PAYMENT_CHECKOUT_URL', 'http://127.0.0.1:5173/checkout'),

    'description_prefix' => env('BOOKING_PAYMENT_DESCRIPTION_PREFIX', 'Submeet ticket'),

    'tickets' => [
        'disk' => env('BOOKING_TICKETS_DISK', env('FILESYSTEM_DISK', 'local')),
        'directory' => env('BOOKING_TICKETS_DIRECTORY', 'tickets'),
    ],

    'yookassa' => [
        'base_url' => env('YOOKASSA_BASE_URL', 'https://api.yookassa.ru/v3'),
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'capture' => (bool) env('YOOKASSA_CAPTURE', true),
    ],
];
