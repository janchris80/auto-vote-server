<?php

namespace App\Jobs\V2;

use App\Models\UpvoteComment;
use App\Models\UpvotedComment;
use App\Models\UpvoteLater;
use App\Traits\HelperTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessUpvoteCommentsJob implements ShouldQueue
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
                $this->processCommentBlockOperations(
                    $operation['parent_author'],
                    $operation['author'],
                    $operation['permlink'],
                    $operation['parent_permlink'],
                );
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function processCommentBlockOperations($voter, $commenter, $permlink, $parent_permlink)
    {
        try {
            $fetchUpvotedComments = UpvotedComment::query()
                ->where('voter', $voter)
                ->where('author', $commenter)
                ->where('permlink', $permlink)
                ->first();

            if ($fetchUpvotedComments) {
                return null;
            }

            $fetchUpvoteComments = UpvoteComment::query()
                ->select(
                    'author', // voter and author of the post
                    'commenter', // followed user, who commented on your post
                    'voter_weight',
                )
                ->where('is_enable', 1)
                ->where('author', $voter)
                ->where('commenter', $commenter)
                ->get();

            foreach ($fetchUpvoteComments as $comment) {
                $votingTime = $comment->voting_time;

                $checkLimits = $this->checkLimits($comment->author, $commenter, $permlink, $comment->voter_weight);

                if ($checkLimits && $votingTime === 0) {
                    $this->jobs->push(new VotingJob([
                        'voter' => $comment->author, // voter and author of the post
                        'author' => $commenter, // followed user, who commented on your post
                        'permlink' => $permlink, // the permlink of the commenter on the post
                        'weight' => $comment->voter_weight,
                        'trailer_type' => 'upvote_comment',
                    ]));
                }

                if ($votingTime > 0) {
                    UpvoteLater::updateOrCreate(
                        [
                            'voter' => $comment->author,
                            'author' => $commenter,
                            'permlink' => $permlink,
                        ],
                        [
                            'weight' => $comment->voter_weight,
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
