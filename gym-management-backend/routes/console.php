<?php

use Illuminate\Support\Facades\Schedule;

// Door punches → attendance. Needs the server cron:
//   * * * * * cd /path/to/gym-management-backend && php artisan schedule:run
// The command itself does nothing unless VFT_MODE=live.
Schedule::command('vft:sync-attendance')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
