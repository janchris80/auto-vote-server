<?php

namespace App\Jobs\V2;

use App\Models\Downvote;
use App\Models\UpvoteCurator;
use App\Models\UpvoteLater;
use App\Traits\HelperTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessDownvotesJob implements ShouldQueue
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
            $getContent = $this->getContent($author, $permlink);

            if (!$getContent) {
                return null;
            }

            $created = Carbon::parse($getContent['created']);
            $now = Carbon::now();
            // Calculate the time difference in seconds
            $timeDifferenceInSeconds = $now->diffInSeconds($created);

            // Skip posts which are older than 6.5 days (561600 seconds)
            if ($timeDifferenceInSeconds < 561600 && $getContent['parent_author'] === '') {
                $fetchUpvoteCurators = Downvote::query()
                    ->select(
                        // 'author', // followed user
                        'voter', // voter
                        'voter_weight',
                        'voting_type',
                    )
                    ->where('is_enable', 1)
                    ->where('author', $followed)
                    ->get();

                $rootAuthor = $getContent['root_author'];

                $activeVotes = collect($getContent['active_votes'])->pluck('voter');

                foreach ($fetchUpvoteCurators as $downvote) {
                    $follower = $downvote->voter;
                    $votingTime = $downvote->voting_time;

                    if ($rootAuthor !== $follower) {
                        $hasVoted = $activeVotes->contains($follower);

                        if (!$hasVoted) {
                            $voterWeight = $downvote->voting_type === 'scaled'
                            ? (int)(($downvote->voter_weight / 10000) * $weight)
                                : $downvote->voter_weight;

                            $checkLimits = $this->checkLimits($follower, $author, $permlink, $voterWeight);

                            if ($checkLimits && $votingTime === 0) {
                                $this->jobs->push(new VotingJob([
                                    'voter' => $follower,
                                    'author' => $author,
                                    'permlink' => $permlink,
                                    'weight' => -$voterWeight,
                                    'trailer_type' => 'curation',
                                ]));
                            }

                            if ($votingTime > 0) {
                                UpvoteLater::updateOrCreate(
                                    [
                                        'voter' => $follower,
                                        'author' => $author,
                                        'permlink' => $permlink,
                                    ],
                                    [
                                        'weight' => -$voterWeight,
                                        'time_to_vote' => now()->addMinutes($votingTime),
                                    ]
                                );
                            }
                        }
                    }

                    // Log::info('ProcessUpvoteCuratorsJob ' . $follower, [
                    //     'hasVoted' => $hasVoted,
                    //     'checkLimits' => $checkLimits ?? false,
                    //     'curator' => $curator
                    // ]);
                }

                // Log::info('ProcessUpvoteCuratorsJob count: ' . $this->jobs->count(), [$this->jobs->all()]);
                if ($this->jobs->count()) {
                    $this->processBatchVotingJob($this->jobs->all());
                    $this->jobs = collect();
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
