<?php

return [

    'username' => env('AT_USERNAME'),
    'api_key' => env('AT_API_KEY'),
    'environment' => env('AT_ENVIRONMENT', 'sandbox'), // sandbox | production
    'service_code' => env('AT_SERVICE_CODE'), // e.g. *384*1234#

    // Checked as a `?secret=` query param on the webhook URL you register
    // with Africa's Talking. Empty (sandbox/local convenience) allows all —
    // must be set in production.
    'shared_secret' => env('AT_SHARED_SECRET'),

    // AT's own session window is short; PruneUssdSessions expires anything
    // idle longer than this.
    'session_ttl_seconds' => (int) env('AT_SESSION_TTL_SECONDS', 180),

];
