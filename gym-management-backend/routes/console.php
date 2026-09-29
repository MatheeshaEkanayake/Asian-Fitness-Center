<?php

use Illuminate\Support\Facades\Schedule;

// Door punches arrive by webhook
// (POST /api/vft/webhook/{secret}, see VftWebhookController).

// Needs the server cron: * * * * * php artisan schedule:run
Schedule::command('members:purge-archived')->dailyAt('02:00')->withoutOverlapping();
