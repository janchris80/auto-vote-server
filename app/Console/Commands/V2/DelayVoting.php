<?php

namespace App\Console\Commands\V2;

use App\Jobs\V2\ProcessDownvotesJob;
use App\Jobs\V2\ProcessUpvoteCommentsJob;
use App\Jobs\V2\ProcessUpvoteCuratorsJob;
use App\Jobs\V2\ProcessUpvoteLatersJob;
use App\Jobs\V2\ProcessUpvotePostsJob;
use App\Jobs\V2\VotingJob;
use App\Models\UpvoteLater;
use App\Traits\HelperTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DelayVoting extends Command
{
    use HelperTrait;

    protected $signature = 'app:delay-voting';
    protected $description = 'Process the delay voting';

    public function handle()
    {
        $jobs = collect();
        $upvoteLaters = UpvoteLater::where('time_to_vote', '<', now())->get();

        foreach ($upvoteLaters as $upvoteLater) {
            $jobs->push(new VotingJob([
                'voter' => $upvoteLater->voter,
                'author' => $upvoteLater->author,
                'permlink' => $upvoteLater->permlink,
                'weight' => $upvoteLater->weight,
            ]));

            // Optionally, you can delete the processed record here
            $upvoteLater->delete();
        }

        if ($jobs->count()) {
            $this->processBatchVotingJob($jobs->all());
            $jobs = collect();
        }
    }
}
