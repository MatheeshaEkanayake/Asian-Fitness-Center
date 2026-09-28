<?php

/**
 * VFT GYM API — cloud API in front of the ZKTeco door/fingerprint device.
 *
 * The app never talks to the device directly: it calls the VFT cloud
 * (App\Services\Vft\VftApiClient), which forwards commands to the device
 * when it next checks in.
 *
 * Endpoints follow the VFT_GYM_API_V2.0 Postman collection
 * (docs/VFT_GYM_API_V2.0.postman_collection (5).json).
 *
 * VFT_MODE controls whether anything is actually sent:
 *   off  — nothing is queued or sent (the app works exactly as before)
 *   log  — device actions are written to storage/logs/vft.log but not sent
 *          (dry run, useful while the device is offline)
 *   live — actions are sent to VFT
 */
return [

    'mode' => env('VFT_MODE', 'off'),

    'base_url' => rtrim(env('VFT_BASE_URL', 'https://acccloudlk.com'), '/'),

    'email' => env('VFT_EMAIL'),
    'password' => env('VFT_PASSWORD'),

    // Header the sign-in token is sent in (VFT_GYM_API_V2.0 collection).
    'token_header' => env('VFT_TOKEN_HEADER', 'ApiToken'),

    // Serial number of the door device (Menu › System Info on the device,
    // or `php artisan vft:check`).
    'default_device_sn' => env('VFT_DEFAULT_DEVICE_SN'),

    // HTTP behaviour. Retries only cover connection failures, never 4xx/5xx.
    'timeout' => (int) env('VFT_TIMEOUT', 15),
    'retries' => (int) env('VFT_RETRIES', 2),
    'retry_sleep_ms' => (int) env('VFT_RETRY_SLEEP_MS', 500),

    // Used when the token's own expiry can't be read. Tokens currently last
    // 1 hour; we refresh 5 minutes early.
    'token_ttl_seconds' => (int) env('VFT_TOKEN_TTL', 3300),

    // Device time zone: access dates are calculated in it and door punches
    // are recorded in it.
    'timezone' => env('VFT_TIMEZONE', 'Asia/Colombo'),

    // Punches arrive by webhook: VFT POSTs them to
    //   {APP_URL}/api/vft/webhook/{VFT_WEBHOOK_SECRET}
    // (register that URL in the VFT portal). Without a secret the webhook
    // is switched off and answers 404.
    'webhook_secret' => env('VFT_WEBHOOK_SECRET'),

    // Every punch counts as attendance except these `Event` codes (VFT
    // hasn't documented them yet; each punch's raw codes are kept in
    // device_punches so this list can be filled in from real data).
    'attendance_excluded_events' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('VFT_ATTENDANCE_EXCLUDED_EVENTS', ''))),
        'strlen',
    )),

    // How long the default device's area id (looked up from GET /api/device)
    // is cached. New people are added to that area.
    'area_cache_seconds' => (int) env('VFT_AREA_CACHE_SECONDS', 86400),

];
