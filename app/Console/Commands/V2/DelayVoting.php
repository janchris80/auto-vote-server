<?php

namespace App\Console\Commands\V2;

use App\Jobs\V2\VotingJob;
use App\Models\UpvoteLater;
use App\Traits\HelperTrait;
use Illuminate\Console\Command;

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
