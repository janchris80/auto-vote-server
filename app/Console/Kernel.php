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
        $schedule->command('broadcast:vote')->everyMinute();
        $schedule->command('broadcast:claim-rewards')->everyFifteenMinutes();
        // $schedule->command('publish:test')->everyMinute();

        // $schedule->call(function () {
        //     $loop = Factory::create();

        //     $loop->addPeriodicTimer(1, function () {
        //         // Your code here, runs every second
        //         $result = Http::post('https://rpc.d.buzz/', [
        //             'jsonrpc' => '2.0',
        //             'method' => 'condenser_api.get_dynamic_global_properties',
        //             'params' => [], //
        //             'id' => 1,
        //         ])->json()['result'];

        //         $headBlockNumber = $result['head_block_number'];
        //         $lastBlock = 0;

        //         if ($result && $headBlockNumber) {
        //             if ($headBlockNumber > $lastBlock) {
        //                 $lastBlock = $headBlockNumber;
        //             }
        //         }

        //         $getBlockResult = Http::post('https://rpc.d.buzz/', [
        //             'jsonrpc' => '2.0',
        //             'method' => 'condenser_api.get_block',
        //             'params' => [$lastBlock], //
        //             'id' => 1,
        //         ])->json()['result'];

        //         $operations = [];

        //         foreach ($getBlockResult['transactions'] as $transaction) {
        //             $operations[] = $transaction['operations'];
        //         }

        //         // dump($operations);
        //         foreach ($operations as $operation) {
        //             $op = $operation[0];
        //             if ($op[0] === 'comment' && $op[1]['parent_author'] !== '') {
        //                 Log::debug('Result', [$op[1]['parent_author'], $op[1]['author'], $op[1]['permlink'], $op[1]['parent_permlink']]);
        //             }
        //         }
        //     });

        //     $loop->run();

        // })->at('17:57');
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
