<?php

namespace App\Jobs\V2;

use App\Models\UpvoteCurator;
use App\Models\VoteLog;
use App\Traits\HelperTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Termwind\Components\BreakLine;

class ProcessUpvoteCuratorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

    public $operations;
    public Collection $jobs;

    public function __construct($operations)
    {
        $this->operations = $operations;
        $this->jobs = collect();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            foreach ($this->operations as $operation) {
                $this->processVoteBlockOperations(
                    $operation['voter'],
                    $operation['author'],
                    $operation['permlink'],
                    $operation['weight'],
                );
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function processVoteBlockOperations($followed, $author, $permlink, $weight)
    {
        try {
            // $fetchVoteLogs = VoteLog::where('voter', $followed)
            //     ->where('author', $author)
            //     ->where('permlink', $permlink)
            //     ->where('trailer_type', 'upvote_comment')
            //     ->first();

            // if ($fetchVoteLogs) {
            //     return null;
            // }

            $getContent = $this->getContent($author, $permlink);

            if (!$getContent) {
                return null;
            }

            if ($getContent['parent_author'] === '') {
                $fetchUpvoteCurators = UpvoteCurator::query()
                    ->select(
                        'author', // followed user
                        'voter', // voter
                        'voter_weight',
                        'voting_type',
                    )
                    ->where('is_enable', 1)
                    ->where('author', $followed)
                    ->get();

                foreach ($fetchUpvoteCurators as $curator) {
                    $voted = false;
                    $follower = $curator->voter;
                    foreach ($getContent['active_votes'] as $activeVote) {
                        if ($activeVote['voter'] === $curator->voter) {
                            $voted = true;
                            break;
                        }
                    }

                    if (!$voted) {
                        $voterWeight = $curator->voter_weight;

                        if ($curator->voting_type === 'scaled') {
                            $voterWeight = (int)(($weight / 10000) * $weight);
                        }

                        $checkLimits = $this->checkLimits($follower, $author, $permlink, $voterWeight);

                        if ($checkLimits) {
                            $this->jobs->push(new UpvoteCuratorsJob([
                                'voter' => $follower,
                                'author' => $author,
                                'weight' => $voterWeight,
                                'permlink' => $permlink,
                            ]));
                        }
                    }
                }

                if (count($this->jobs)) {
                    // Upvote comments
                    $this->dispatchSync($this->jobs->toArray());
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
