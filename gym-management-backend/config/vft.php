<?php

/**
 * VFT GYM API — cloud API in front of the ZKTeco door/fingerprint device.
 *
 * The app never talks to the device directly: it calls the VFT cloud
 * (App\Services\Vft\VftApiClient), which forwards commands to the device
 * when it next checks in.
 *
 * VFT_MODE controls whether anything is actually sent:
 *   off  — nothing is queued or sent (the app works exactly as before)
 *   log  — device commands are built and written to storage/logs/vft.log,
 *          but not sent (dry run, useful while the device is offline)
 *   live — commands are sent to VFT
 */
return [

    'mode' => env('VFT_MODE', 'off'),

    'base_url' => rtrim(env('VFT_BASE_URL', 'https://acccloudlk.com'), '/'),

    'email' => env('VFT_EMAIL'),
    'password' => env('VFT_PASSWORD'),

    // The doc uses both `apitoken` and `ApiToken`; the live server accepts
    // `ApiToken`.
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

    // Door access granted to members/staff (ZKTeco access-control ids).
    'door_id' => (int) env('VFT_DOOR_ID', 1),
    'access_timezone_id' => (int) env('VFT_ACCESS_TIMEZONE_ID', 1),
    'user_group' => (int) env('VFT_USER_GROUP', 1),

    // Transaction event types that count as "came in" for attendance.
    // 0 = normal verify & open. Denied attempts use other codes.
    'attendance_event_types' => array_filter(explode(',', env('VFT_ATTENDANCE_EVENT_TYPES', '0')), 'strlen'),

];
