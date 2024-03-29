<?php

namespace App\Console;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use React\EventLoop\Factory;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('broadcast:voting')->everyMinute();

        // $schedule->command('publish:test')->everyMinute();
        // $schedule->command('app:test')->everySecond();
        // $schedule->command('app:update-cache-command')->everyFiveMinutes();
        $schedule->command('stream:block')->everySecond();
        $schedule->command('broadcast:claim-rewards')->everyFifteenMinutes();
        $schedule->command('app:update-community-lists-command')->daily();
        $schedule->command('app:delay-voting')->everyMinute();
        $schedule->command('generate:log-files')->twiceDaily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
