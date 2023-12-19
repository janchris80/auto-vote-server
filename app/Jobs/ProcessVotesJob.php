<?php

namespace App\Jobs;

use App\Models\Follower;
use App\Traits\HelperTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessVotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

    const CONDENSER_API_GET_ACCOUNTS = 'condenser_api.get_accounts';
    const CONDENSER_API_GET_ACCOUNT_HISTORY = 'condenser_api.get_account_history';
    const CONDENSER_API_GET_CONTENT_REPLIES = 'condenser_api.get_content_replies';
    const CONDENSER_API_GET_ACTIVE_VOTES = 'condenser_api.get_active_votes';
    const BRIDGE_GET_ACCOUNT_POSTS = 'bridge.get_account_posts';
    const RC_API_FIND_RC_ACCOUNTS = 'rc_api.find_rc_accounts';

    protected $followers;
    public $timeout = 300; // in seconds

    public function __construct($followers)
    {
        $this->followers = $followers;
    }

    public function handle()
    {
        try {
            // Check if the 'condenser_api.get_accounts' method is available
            if ($this->canMakeRequest(self::CONDENSER_API_GET_ACCOUNTS)) {
                // Extract unique usernames
                $followers = $this->followers;
                $usernames = $followers->pluck('follower.username')->unique()->all();
                $canVoteUsernames = $this->getEligibleUsernames($usernames);

                // Retrieve account information
                $accounts = $this->getApiData(
                    self::CONDENSER_API_GET_ACCOUNTS,
                    [$canVoteUsernames, false]
                );

                foreach ($accounts as $account) {
                    $mana = $this->processAccountCurrentMana($account);
                    $this->processVotes($followers, $mana);
                }
            }
        } catch (\Throwable $th) {
            // Handle the exception
            dump($th->getMessage());
            Log::error('Error processing followers: ' . $th->getMessage());
        }
    }

    protected function processVotes($followers, $mana)
    {
        $votes = [];

        foreach ($followers as $user) {
            $followedUser = $user->user->username;
            $voter = $user->follower->username;
            $votingType = $user->voting_type;
            $voterWeight = $user->weight;
            $voterDownvoteManaLimit = $user->follower->limit_downvote_mana;
            $voterUpvoteManaLimit = $user->follower->limit_upvote_mana;
            $method = $user->trailer_type; // curation, downvote, upvote_post, upvote_comment
            $limitMana = $method === 'downvote' ? $voterDownvoteManaLimit : $voterUpvoteManaLimit;
            $currentMana = $method === 'downvote' ? $mana['downvote'] : $mana['upvote'];
            $canVote = $currentMana > $limitMana;

            if ($canVote) {
                Log::info("$voter can vote? ", [$currentMana > $limitMana, $currentMana, $limitMana]);
                //dump('Processing ' . $voter . ' - ' . $method);

                if (in_array($method, ['curation', 'downvote'])) {
                    $history = $this->getAccountHistory($user->user->username);

                    foreach ($history as $tx) {
                        $weight = $this->calculateVotingWeight($voterWeight, $tx['weight'], $votingType);

                        $tx['voter'] = $voter;
                        $tx['weight'] = $weight;
                        $tx['limitMana'] = $limitMana;

                        $votes[] = $tx;

                        // $activeVotes = $this->getActiveVotes($tx['author'], $tx['permlink']);
                        // $isVoted = $activeVotes->contains('voter', $voter);

                        // if (!$isVoted) {
                        $this->dispatchUpvoteJob(
                            $voter,
                            $tx['author'],
                            $tx['permlink'],
                            $weight,
                            $limitMana,
                            $method
                        );
                        // }
                    }
                }

                if ($method === 'upvote_post') {
                    $posts = $this->getAccountPost($followedUser);

                    foreach ($posts as $post) {
                        if ($post['author'] === $followedUser) {

                            // $activeVotes = $this->getActiveVotes($post['author'], $post['permlink']);
                            // $isVoted = $activeVotes->contains('voter', $voter);

                            // if (!$isVoted) {
                            $this->dispatchUpvoteJob(
                                $voter,
                                $post['author'],
                                $post['permlink'],
                                $voterWeight,
                                $limitMana,
                                $method
                            );
                            // }
                        }
                    }
                }

                if ($method === 'upvote_comment') {
                    $userPosts = $this->getAccountPost($voter);

                    foreach ($userPosts as $post) {
                        $replies = $this->getContentReplies($voter, $post['permlink']);

                        if (count($replies)) {
                            foreach ($replies as $reply) {
                                if ($reply['author'] === $followedUser) {
                                    $votes[] = [
                                        'voter' => $voter,
                                        'author' => $reply['author'],
                                        'permlink' => $reply['permlink'],
                                        'weight' => $voterWeight,
                                        'limitMana' => $limitMana,
                                        'method' => $method,
                                    ];

                                    // $activeVotes = $this->getActiveVotes($reply['author'], $reply['permlink']);
                                    // $isVoted = $activeVotes->contains('voter', $voter);

                                    // if (!$isVoted) {
                                    $this->dispatchUpvoteJob(
                                        $voter,
                                        $reply['author'],
                                        $reply['permlink'],
                                        $voterWeight,
                                        $limitMana,
                                        $method
                                    );
                                    // }
                                }
                            }
                        }
                    }
                }
            }

            //dump('Not enough mana to process ' . $voter . ' - ' . $method);
        }
    }

    protected function dispatchUpvoteJob($voter, $author, $permlink, $weight, $limitMana, $method)
    {
        $toVote = collect(compact('voter', 'author', 'permlink', 'weight', 'limitMana', 'method'));
        ProcessUpvoteJob::dispatch($toVote)->onQueue('voting');
    }

    protected function getEligibleUsernames($usernames)
    {
        $eligibleUsernames = [];

        $resourceCredits = $this->getApiData(self::RC_API_FIND_RC_ACCOUNTS, ['accounts' => array_values($usernames)]);

        foreach ($resourceCredits['rc_accounts'] ?? [] as $resourceCredit) {
            if ($this->checkResourceCredit($resourceCredit)) {
                $eligibleUsernames[] = $resourceCredit['account'];
            }
        }

        return $eligibleUsernames;
    }

    protected function calculateVotingWeight($userWeight, $authorWeight, $votingType)
    {
        $convertHivePercentage = 10000; // 100%
        $percentage = $userWeight; // 1%
        $baseValue = $authorWeight; // 13%
        $method = strtolower($votingType);
        $result = 0;

        if ($method === 'fixed') {
            $result = $percentage;
        }

        if ($method === 'scaled') {
            $result = (($percentage / $convertHivePercentage) * $baseValue);
        }

        return intval($result);
    }

    protected function getActiveVotes($author, $permlink)
    {
        $response = $this->getApiData(
            self::CONDENSER_API_GET_ACTIVE_VOTES,
            [$author, $permlink]
        );

        return collect($response);
    }

    protected function getContentReplies($username, $permlink)
    {
        $response = $this->getApiData(
            self::CONDENSER_API_GET_CONTENT_REPLIES,
            [$username, $permlink]
        );

        return $response;
    }

    protected function getAccountPost($username)
    {
        $response = $this->getApiData(
            self::BRIDGE_GET_ACCOUNT_POSTS,
            [
                "sort" => "posts",
                "account" => $username,
                "limit" => 10,
            ]
        );

        return $response;
    }

    protected function getAccountHistory($username)
    {
        $response = $this->getApiData(
            self::CONDENSER_API_GET_ACCOUNT_HISTORY,
            [$username, -1, 150, 1]
        );

        $voteOps = collect($response)
            ->filter(function ($tx) use ($username) {
                return $tx[1]['op'][1]['voter'] === $username;
            })
            ->map(function ($tx) {
                return $tx[1]['op'][1];
            });

        return $voteOps;
    }

    protected function checkResourceCredit($data)
    {
        $currentMana = $data['rc_manabar']['current_mana'];
        $maxMana = $data['max_rc'];
        $name = $data['account'];

        // Calculate the percentage
        $percentage = ($currentMana / $maxMana) * 100;
        $percent = number_format($percentage, 2);
        // Log::info($name . ' resource credit: ', [(float) $percent > 1, (float) $percent, $percentage]);

        return (float) $percent > 1;
    }

    protected function processAccountCurrentMana($account)
    {
        // Extracting and processing account details
        $delegated = floatval(str_replace('VESTS', '', $account['delegated_vesting_shares']));
        $received = floatval(str_replace('VESTS', '', $account['received_vesting_shares']));
        $vesting = floatval(str_replace('VESTS', '', $account['vesting_shares']));
        $withdrawRate = 0;

        if (intval(str_replace('VESTS', '', $account['vesting_withdraw_rate'])) > 0) {
            $withdrawRate = min(
                intval(str_replace('VESTS', '', $account['vesting_withdraw_rate'])),
                intval(($account['to_withdraw'] - $account['withdrawn']) / 1000000)
            );
        }

        $totalvest = $vesting + $received - $delegated - $withdrawRate;
        $maxMana = $totalvest * pow(10, 6);
        $maxManaDown = $maxMana * 0.25;

        $delta = time() - $account['voting_manabar']['last_update_time'];
        $currentMana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);

        $deltaDown = time() - $account['downvote_manabar']['last_update_time'];
        $currentManaDown = $account['downvote_manabar']['current_mana'] + ($deltaDown * $maxManaDown / 432000);

        $percentage = round($currentMana / $maxMana * 10000);
        $percentageDown = round($currentManaDown / $maxManaDown * 10000);

        if (!is_finite($percentage)) $percentage = 0;
        if ($percentage > 10000) $percentage = 10000;
        elseif ($percentage < 0) $percentage = 0;

        if (!is_finite($percentageDown)) $percentageDown = 0;
        if ($percentageDown > 10000) $percentageDown = 10000;
        elseif ($percentageDown < 0) $percentageDown = 0;

        $upvotePower = intval($percentage);
        $downvotePower = intval($percentageDown);

        return [
            'upvote' => $upvotePower,
            'downvote' => $downvotePower,
            'name' => $account['name'],
        ];
    }
}
