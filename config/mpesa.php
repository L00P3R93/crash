<?php

return [

    'env' => env('MPESA_ENV', 'sandbox'), // sandbox | production

    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    'shortcode' => env('MPESA_SHORTCODE'),
    'passkey' => env('MPESA_PASSKEY'),

    'stk_callback_url' => env('MPESA_STK_CALLBACK_URL'),
    'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),
    'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),

    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    // Encrypted with Safaricom's public certificate before use in production —
    // see B2cService::securityCredential().
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),

    // Comma-separated Safaricom source IPs allowed to hit the webhook routes.
    // Left empty (sandbox/local convenience) means "allow all" — must be set
    // in production, see VerifyMpesaIp.
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MPESA_ALLOWED_IPS', ''))
    ))),

    'min_topup' => (float) env('MPESA_MIN_TOPUP', 50),
    'max_topup' => (float) env('MPESA_MAX_TOPUP', 10000),

    'min_withdrawal' => (float) env('MPESA_MIN_WITHDRAWAL', 50),
    'max_withdrawal' => (float) env('MPESA_MAX_WITHDRAWAL', 10000),

];
