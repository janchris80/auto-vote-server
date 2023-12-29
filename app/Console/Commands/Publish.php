<?php

namespace App\Console\Commands;

use App\Jobs\V1\ProcessUpvoteJob;
use App\Jobs\V1\SendDiscordNotificationJob;
use App\Models\Follower;
use App\Models\User;
use App\Models\Vote;
use App\Traits\HelperTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Throwable;

use function Laravel\Prompts\error;

class Publish extends Command
{
    use HelperTrait;

    protected $signature = 'publish:test';
    protected $description = 'Command description';

    public function handle()
    {
        foreach(User::all() as $user) {
        }
        $this->checkLimits('iamjc93', '', '', 5000);
    }

    public function checkLimits($voter, $author, $permlink, $weight)
    {
        try {
            // Fetch user's power limit from the database
            $powerlimit = User::select('limit_upvote_mana')->where('username', $voter)->value('limit_upvote_mana');

            // Fetch user details from the blockchain (adjust the following code based on your actual implementation)
            $account = $this->getAccounts($voter)->first();

            // On any error, account will be null
            if (!$account) {
                return null;
            }

            $getDynamicglobalProperties = $this->getDynamicGlobalProperties();
            $tvfs = (int)str_replace('HIVE', '', $getDynamicglobalProperties['total_vesting_fund_hive']);
            $tvs = (int)str_replace('VESTS', '', $getDynamicglobalProperties['total_vesting_shares']);

            // Extract necessary information from the user details
            if ($tvfs && $tvs) {

                // Calculating total SP to check against limitation
                $delegated = (int) str_replace('VESTS', '', $account['delegated_vesting_shares']);
                $received = (int) str_replace('VESTS', '', $account['received_vesting_shares']);
                $vesting = (int) str_replace('VESTS', '', $account['vesting_shares']);
                $totalvest = $vesting + $received - $delegated;
                $sp = $totalvest * ($tvfs / $tvs);
                $sp = round($sp, 2);

                // Calculating Mana to check against limitation
                $withdrawRate = 0;

                if ($account['vesting_withdraw_rate'] > 0) {
                    $withdrawRate = min(
                        $account['vesting_withdraw_rate'],
                        ($account['to_withdraw'] - $account['withdrawn']) / 1000000
                    );
                }

                $maxMana = ($totalvest - $withdrawRate) * pow(10, 6);

                if ($maxMana === 0) {
                    return null;
                }

                $delta = Carbon::now()->timestamp - $account['voting_manabar']['last_update_time'];
                $currentMana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);
                $percentage = round($currentMana / $maxMana * 10000);

                if (!is_finite($percentage)) {
                    $percentage = 0;
                }

                if ($percentage > 10000) {
                    $percentage = 10000;
                } elseif ($percentage < 0) {
                    $percentage = 0;
                }

                $powernow = round($percentage / 100, 2);

                if ($powernow > $powerlimit) {
                    if (($powernow / 100) * ($weight / 10000) * $sp > 3) {
                        // Don't broadcast upvote if sp*weight*power < 3
                        return 1;
                    }

                    return null;
                }

                return null;
            }
        } catch (Exception $e) {
            dd($e->getMessage());
            return null;
        }
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
            $followerId = $follower->id;
            $hasEnoughMana = false;
            // $discordWebhookUrl = $follower->follower->discord_webhook_url;
            $voter = $follower->follower->username;
            $followedAuthor = $follower->user->username;
            // $voterUserId = $follower->follower->id;
            $trailerType = $follower->trailer_type; // curation, downvote, upvote_post, upvote_comment
            $voterWeight = $follower->weight;
            $votingType = $follower->voting_type; // scaled or fixed,
            $lastVotedAt = $follower->last_voted_at ?? now();

            $limitUpvoteMana = $follower->follower->limit_upvote_mana;
            $limitDownvoteMana = $follower->follower->limit_downvote_mana;

            $limitMana = $trailerType === 'downvote' ? $limitDownvoteMana : $limitUpvoteMana;

            $hasEnoughResourceCredit = $this->hasEnoughResourceCredit($voter);
            $rcLeft = intval($this->getResourceCredit() * 100);

            if (!$hasEnoughResourceCredit) {
                return;
            }

            $account = $this->getAccounts($voter)->first();
            $hasEnoughMana = $this->hasEnoughMana($account, $trailerType, $limitMana);
            $manaLeft = $this->getCurrentMana();

            if (!$hasEnoughMana) {
                return;
            }

            if (in_array($trailerType, ['curation', 'downvote'])) {
                $jobs = collect();

                $history = Cache::remember('get_vote_history_' . $followedAuthor, 60, function () use ($followedAuthor) {
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
                            'voterWeight' => $voterWeight,
                            'manaLeft' => $manaLeft,
                            'rcLeft' => $rcLeft,
                            'votedAt' => now(),
                            'followerId' => $followerId,
                        ]);

                        $jobs->push(new ProcessUpvoteJob($toVote));
                    }
                }

                if ($jobs->count()) {
                    $this->processBatchVotingJob($jobs->toArray());
                }

                return;
            }

            if ($trailerType === 'upvote_post') {
                $jobs = collect();

                $posts = Cache::remember('get_account_post_' . $followedAuthor, 60, function () use ($followedAuthor) {
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
                            'created' => $post['created'],
                        ];
                    });

                foreach ($filteredPosts as $post) {

                    $voteTimestamp = strtotime($post['created']);

                    if ($voteTimestamp >= strtotime($lastVotedAt) && $voteTimestamp <= time()) {

                        if ($post['author'] === $followedAuthor) {

                            $weight = $this->calculateVotingWeight($voterWeight, $follower->weight, $votingType);

                            $toVote = collect([
                                'voter' => $voter,
                                'author' => $post['author'],
                                'permlink' => $post['permlink'],
                                'weight' => $weight,
                                'limitMana' => $limitMana,
                                'votingType' => $votingType,
                                'trailerType' => $trailerType,
                                'voterWeight' => $voterWeight,
                                'manaLeft' => $manaLeft,
                                'rcLeft' => $rcLeft,
                                'votedAt' => now(),
                                'followerId' => $followerId,
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

            if ($trailerType === 'upvote_comment') {
                $jobs = collect();
                $posts = Cache::remember('get_account_post_' . $voter, 60, function () use ($voter) {
                    return $this->getAccountPost($voter);
                });

                $filteredPosts = $posts
                    ->filter(function ($post) use ($voter) {
                        return  $post['author'] === $voter;
                    })
                    ->map(function ($post) {
                        return [
                            'author' => $post['author'],
                            'permlink' => $post['permlink'],
                            'created' => $post['created'],
                        ];
                    });

                foreach ($filteredPosts as $post) {
                    $replies = Cache::remember('get_content_replies_' . $voter, 60, function () use ($voter, $post) {
                        return $this->getContentReplies($voter, $post['permlink']);
                    });

                    $filteredReplies = $replies
                        ->filter(function ($reply) use ($voter, $followedAuthor) {
                            // Check if any "active_votes" has the specified voter
                            $hasVoted = collect($reply['active_votes'])->pluck('voter')->contains($voter);

                            // If not voted, include the item
                            return !$hasVoted && $reply['author'] === $followedAuthor;
                        })
                        ->map(function ($reply) {
                            return [
                                'author' => $reply['author'],
                                'permlink' => $reply['permlink'],
                                'created' => $reply['created'],
                            ];
                        });

                    foreach ($filteredReplies as $reply) {

                        if ($reply['author'] === $followedAuthor) {

                            $voteTimestamp = strtotime($reply['created']);

                            if ($voteTimestamp >= strtotime($lastVotedAt) && $voteTimestamp <= time()) {
                                $weight = $this->calculateVotingWeight($voterWeight, $follower->weight, $votingType);

                                $toVote = collect([
                                    'voter' => $voter,
                                    'author' => $reply['author'],
                                    'permlink' => $reply['permlink'],
                                    'weight' => $weight,
                                    'limitMana' => $limitMana,
                                    'votingType' => $votingType,
                                    'trailerType' => $trailerType,
                                    'voterWeight' => $voterWeight,
                                    'manaLeft' => $manaLeft,
                                    'rcLeft' => $rcLeft,
                                    'votedAt' => now(),
                                    'followerId' => $followerId,
                                ]);

                                $jobs->push(new ProcessUpvoteJob($toVote));
                            }
                        }
                    }
                }

                if ($jobs->count()) {
                    $this->processBatchVotingJob($jobs->toArray());
                }

                return;
            }
        } catch (\Exception $e) {
            Log::error("Job failed for voter " . $voter . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
