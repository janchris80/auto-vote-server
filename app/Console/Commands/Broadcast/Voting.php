<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\ProcessUpvoteJob;
use App\Jobs\ProcessVotesJob;
use App\Models\Follower;
use App\Models\Vote;
use Illuminate\Console\Command;

class Voting extends Command
{
    protected $signature = 'broadcast:voting';
    protected $description = 'Vote';

    public function handle()
    {
        Follower::query()
            ->whereHas('follower', function ($query) {
                $query->where('is_enable', '=', 1);
            })
            ->with(['user', 'follower'])
            ->where('is_enable', '=', 1)
            ->chunk(100, function ($followers) {
                ProcessVotesJob::dispatch($followers)->onQueue('processing');
            });

        // ProcessUpvoteJob::dispatch($votes)->onQueue('voting');
        Vote::query()
            ->where('is_voted', '=', 0)
            ->chunk(100, function ($votes) {
                ProcessUpvoteJob::dispatch($votes)->onQueue('voting');
            });
    }
}
