<?php

namespace App\Jobs\V2;

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessUpvotePostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

    public $operations;
    public Collection $jobs;

    public function __construct($operations)
    {
        $this->operations = $operations;
        $this->jobs = collect();
    }

    public function handle(): void
    {
        try {
            foreach ($this->operations as $operation) {
                $this->processPostBlockOperations($operation['author'], $operation['permlink']);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    // This function will handle the logic to check and update recent authors
    protected function checkAuthor($author)
    {
        $now = Carbon::now()->timestamp;

        // Get the recent authors from cache or initialize an empty array
        $recentAuthors = Cache::get('recent_authors', []);

        $index = $this->authorIndex();

        if (array_key_exists($author, $recentAuthors)) {
            $lastPostTime = $recentAuthors[$author]['date'];

            if ($now - $lastPostTime > 240) {
                $recentAuthors[$author] = [
                    'date' => $now,
                    'index' => $index,
                ];
                $this->updateIndex();

                Cache::put('recent_authors', $recentAuthors, now()->addMinutes(5));

                return true;
            } else {
                return false;
            }
        } else {
            $recentAuthors[$author] = [
                'date' => $now,
                'index' => $index,
            ];
            $this->updateIndex();

            Cache::put('recent_authors', $recentAuthors, now()->addMinutes(5));

            return true;
        }
    }

    // Function to update the index in a circular manner
    protected function updateIndex()
    {
        $index = Cache::get('recent_authors_index', 0);

        if ($index < $this->maxIndex) {
            $index += 1;
        } else {
            $index = 0;
        }

        Cache::put('recent_authors_index', $index, now()->addMinutes(5));
    }

    // Function to get the current index
    protected function authorIndex()
    {
        return Cache::get('recent_authors_index', 0);
    }

    public function processPostBlockOperations($author, $permlink)
    {
        try {
            // Check author's recent post date
            if (!$this->checkAuthor($author)) {
                // Fetch fanbase data from the database
                return false;
            }

            $getContent = $this->getContent($author, $permlink);

            if ($getContent->count() === 0) {
                return false;
            }

            $results = $this->fetchUpvotePosts($author);
            $activeVotes = collect($getContent['active_votes'])->pluck('voter');

            foreach ($results as $row) {
                $voter = $row->voter;
                $weight = $row->voter_weight;
                $votingTime = $row->voting_time ?? 0;

                $hasVoted = $activeVotes->contains($voter);

                if ($hasVoted) {
                    continue 1;
                }

                // Process upvote right now
                // Check limitations
                $checkLimits = $this->checkLimits($voter, $author, $permlink, $weight);

                // Broadcast upvote if user details are not limited
                if ($checkLimits && $votingTime === 0) {
                    $this->jobs->push(new VotingJob([
                        'voter' => $voter,
                        'author' => $author,
                        'permlink' => $permlink,
                        'weight' => $weight,
                        'trailer_type' => 'upvote_post',
                    ]));
                }

                if ($votingTime > 0) {
                    UpvoteLater::updateOrCreate(
                        [
                            'voter' => $voter,
                            'author' => $author,
                            'permlink' => $permlink,
                        ],
                        [
                            'weight' => $weight,
                            'time_to_vote' => now()->addMinutes($votingTime),
                        ]
                    );
                }
            }

            if ($this->jobs->count()) {
                $this->processBatchVotingJob($this->jobs->all());
                $this->jobs = collect();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
