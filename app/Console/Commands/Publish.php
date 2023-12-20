<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUpvoteJob;
use App\Jobs\SendDiscordNotificationJob;
use App\Models\Follower;
use App\Models\Vote;
use App\Traits\HelperTrait;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Throwable;

class Publish extends Command
{
    use HelperTrait;

    protected $signature = 'publish:test';
    protected $description = 'Command description';

    public function handle()
    {
        Follower::query()
            ->whereHas('follower', function ($query) {
                $query->where('is_enable', '=', 1);
            })
            ->with(['user', 'follower'])
            ->where('is_enable', '=', 1)
            ->chunk(100, function ($followers) {
                // ProcessVotesJob::dispatch($followers)->onQueue('processing');
                $this->processFollowers($followers);
            });
    }

    public function processFollowers($followers)
    {
        foreach ($followers as $follower) {
            $this->processFollower($follower);
            unset($follower);
        };
    }

    public function processFollower($follower)
    {
        try {
            $votes = [];

            // $followerId = $follower->id;
            $hasEnoughMana = false;
            // $discordWebhookUrl = $follower->follower->discord_webhook_url;
            $voter = $follower->follower->username;
            $followedAuthor = $follower->user->username;
            // $voterUserId = $follower->follower->id;
            $trailerType = $follower->trailer_type; // curation, downvote, upvote_post, upvote_comment
            $voterWeight = $follower->weight;
            $votingType = $follower->voting_type; // scaled or fixed,
            $lastVotedAt = $follower->last_voted_at ?? $follower->follower->created_at;

            $limitUpvoteMana = $follower->follower->limit_upvote_mana;
            $limitDownvoteMana = $follower->follower->limit_downvote_mana;

            $limitMana = $trailerType === 'downvote' ? $limitDownvoteMana : $limitUpvoteMana;

            $hasEnoughResourceCredit = $this->hasEnoughResourceCredit($voter);

            if (!$hasEnoughResourceCredit) {
                return;
            }

            $account = $this->getAccounts($voter)->first();
            $hasEnoughMana = $this->hasEnoughMana($account, $trailerType, $limitMana);

            if (!$hasEnoughMana) {
                return;
            }

            Log::info($trailerType);

            if (in_array($trailerType, ['curation', 'downvote'])) {
                $jobs = collect();

                $history = Cache::remember('get_vote_history_' . $followedAuthor, 90, function () use ($followedAuthor) {
                    return $this->getVoteAccountHistory($followedAuthor);
                });

                foreach ($history as $vote) {

                    $voteTimestamp = strtotime($vote['timestamp']);

                    if ($voteTimestamp >= strtotime($lastVotedAt) && $voteTimestamp <= time()) {
                        $hasVote = $this->hasVote($voter, $vote['author'], $vote['permlink']);

                        if ($hasVote) {
                            continue;
                        }

                        $weight = $this->calculateVotingWeight($voterWeight, $vote['weight'], $votingType);

                        $toVote = collect([
                            'voter' => $voter,
                            'author' => $vote['author'],
                            'permlink' => $vote['permlink'],
                            'weight' => $weight,
                            'limitMana' => $limitMana,
                            'votingType' => $votingType,
                            'trailerType' => $trailerType,
                        ]);

                        $jobs->push(new ProcessUpvoteJob($toVote));

                        $follower->last_voted_at = now();
                        $follower->save();
                    }
                }

                if ($jobs->count()) {
                    $this->processBatchVotingJob($jobs->toArray());
                }

                return;
            }

            if ($trailerType === 'upvote_post') {
                $jobs = collect();

                $posts = Cache::remember('get_account_post_' . $followedAuthor, 90, function () use ($followedAuthor) {
                    return $this->getAccountPost($followedAuthor);
                });

                $filteredPosts = $posts
                    ->filter(function ($post) use ($voter, $followedAuthor) {
                        // Check if any "active_votes" has the specified voter
                        $hasVoted = collect($post['active_votes'])->pluck('voter')->contains($voter);

                        // If not voted, include the item
                        return !$hasVoted && $post['author'] === $followedAuthor;
                    })
                    ->map(function ($post) {
                        return [
                            'author' => $post['author'],
                            'permlink' => $post['permlink'],
                        ];
                    });

                foreach ($filteredPosts as $post) {
                    if ($post['author'] === $followedAuthor) {
                        $toVote = collect([
                            'voter' => $voter,
                            'author' => $post['author'],
                            'permlink' => $post['permlink'],
                            'weight' => $follower->weight,
                            'limitMana' => $limitMana,
                            'votingType' => $votingType,
                            'trailerType' => $trailerType,
                        ]);

                        $jobs->push(new ProcessUpvoteJob($toVote));
                    }
                }

                if ($jobs->count()) {
                    // $this->processBatchVotingJob($jobs->toArray());
                }

                return;
            }

            if ($trailerType === 'upvote_comment') {
                $jobs = collect();
                $posts = Cache::remember('get_account_post_' . $voter, 90, function () use ($voter) {
                    return $this->getAccountPost($voter);
                });

                $filteredPosts = $posts
                    ->filter(function ($post) use ($voter) {
                        // Check if any "active_votes" has the specified voter
                        $hasVoted = collect($post['active_votes'])->pluck('voter')->contains($voter);

                        // If not voted, include the item
                        return !$hasVoted && $post['author'] === $voter;
                    })
                    ->map(function ($post) {
                        return [
                            'author' => $post['author'],
                            'permlink' => $post['permlink'],
                        ];
                    });

                foreach ($filteredPosts as $post) {
                    $replies = Cache::remember('get_content_replies_' . $voter, 90, function () use ($voter, $post) {
                        return $this->getContentReplies($voter, $post['permlink']);
                    });

                    $filteredReplies = $replies
                        ->filter(function ($reply) use ($voter, $followedAuthor) {
                            // Check if any "active_votes" has the specified voter
                            $hasVoted = collect($reply['active_votes'])->pluck('voter')->contains($voter);

                            // If not voted, include the item
                            return !$hasVoted && $reply['author'] === $followedAuthor;
                        })
                        ->map(function ($post) {
                            return [
                                'author' => $post['author'],
                                'permlink' => $post['permlink'],
                            ];
                        });

                    foreach ($filteredReplies as $reply) {
                        if ($reply['author'] === $followedAuthor) {
                            $toVote = collect([
                                'voter' => $voter,
                                'author' => $reply['author'],
                                'permlink' => $reply['permlink'],
                                'weight' => $follower->weight,
                                'limitMana' => $limitMana,
                                'votingType' => $votingType,
                                'trailerType' => $trailerType,
                            ]);

                            $jobs->push(new ProcessUpvoteJob($toVote));
                        }
                    }
                }

                if ($jobs->count()) {
                    $this->processBatchVotingJob($jobs->toArray());
                }

                return;
            }
        } catch (\Exception $e) {
            Log::error("Job failed for voter " . $voter . ": " . $e->getMessage());
        }
    }
}
