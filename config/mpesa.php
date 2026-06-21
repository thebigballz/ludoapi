<?php

return [
    'env'                 => env('MPESA_ENV', 'sandbox'),
    'consumer_key'        => env('MPESA_CONSUMER_KEY'),
    'consumer_secret'     => env('MPESA_CONSUMER_SECRET'),
    'shortcode'           => env('MPESA_SHORTCODE'),
    'passkey'             => env('MPESA_PASSKEY'),
    'callback_url'        => env('MPESA_CALLBACK_URL'),
    'b2c_result_url'      => env('MPESA_B2C_RESULT_URL'),
    'b2c_queue_url'       => env('MPESA_B2C_QUEUE_URL'),
    'b2c_initiator'       => env('MPESA_B2C_INITIATOR'),
    'b2c_security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),

    'base_url' => env('MPESA_ENV') === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke',
];