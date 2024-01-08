<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\V1\ProcessVotesJob;
use App\Models\Follower;
use Illuminate\Console\Command;

class Voting extends Command
{
    protected $signature = 'broadcast:voting';
    protected $description = 'Vote';

    public function handle()
    {
        // Follower::query()
        //     ->whereHas('follower', function ($query) {
        //         $query->where('is_enable', '=', 1);
        //     })
        //     ->with(['user', 'follower'])
        //     ->where('is_enable', '=', 1)
        //     ->chunk(100, function ($followers) {
        //         ProcessVotesJob::dispatch($followers)->onQueue('processing');
        //     });

        Follower::query()
            ->where('is_enable', '=', 1)
            ->whereHas('follower', function ($query) {
                $query->where('is_enable', '=', 1);
            })
            ->with(['user', 'follower'])
            ->get()
            ->groupBy('user_id')
            ->each(function ($followers) {
                $followers->chunk(10)
                    ->each(function ($followers) {
                        ProcessVotesJob::dispatch($followers)->onQueue('processing');
                    });
            });
    }
}
