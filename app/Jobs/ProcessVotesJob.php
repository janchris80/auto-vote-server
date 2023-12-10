<?php

namespace App\Jobs;

use App\Models\Follower;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $followers;
    public $tries = 3;
    public $timeout = 300; // in seconds

    public function __construct($followers)
    {
        $this->followers = $followers;
    }

    public function handle()
    {
        $startTime = microtime(true); // Start timer
        Log::info("Starting ProcessVotesJob for " . count($this->followers) . " followers");

        foreach ($this->followers as $follower) {
            $this->processFollower($follower);
            unset($follower);
        };

        Log::info("ProcessVotesJob completed in " . (microtime(true) - $startTime) . " seconds");
    }

    public function getAccountPost($username)
    {
        $response = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'bridge.get_account_posts',
            'params' => [
                "sort" => "posts",
                "account" => $username,
                "limit" => 20,
            ],
            'id' => 1,
        ]);

        return $response;
    }

    public function getContentReplies($username, $permlink)
    {
        $response = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_content_replies',
            'params' => [$username, $permlink],
            'id' => 1,
        ]);

        return $response;
    }

    protected function getAccountHistory($username)
    {
        $response = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_account_history',
            'params' => [$username, -1, 150, 1],
            'id' => 1,
        ]);

        $voteOps = collect($response)
            ->filter(function ($tx) use ($username) {
                return $tx[1]['op'][1]['voter'] === $username;
            })
            ->map(function ($tx) {
                return $tx[1]['op'][1];
            });

        return $voteOps;
    }

    protected function getActiveVotes($author, $permlink)
    {
        $response = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_active_votes',
            'params' => [$author, $permlink],
            'id' => 1,
        ]);

        return collect($response);
    }

    protected function processUpvotes($transactions, $usernameToCheck, $userWeight, $method, $limitMana)
    {
        $votes = [];

        try {
            foreach ($transactions as $tx) {
                if ($this->canMakeRequest('condenser_api.get_active_votes')) {
                    $weight = $this->calculateVotingWeight($userWeight, $tx['weight'], $method);

                    $tx['voter'] = $usernameToCheck;
                    $tx['weight'] = $weight;
                    $tx['limitMana'] = $limitMana;
                    // $tx['author']
                    // $tx['permlink']
                    $votes[] = $tx;
                    $toVote = collect([
                        'voter' => $usernameToCheck,
                        'author' => $tx['author'],
                        'permlink' => $tx['permlink'],
                        'weight' => $weight,
                        'limitMana' => $limitMana,
                        'method' => $method,
                    ]);

                    ProcessUpvoteJob::dispatch($toVote)->onQueue('voting');

                    // Cache::put('last_api_request_time.condenser_api.get_active_votes', now(), 60); // 60 seconds cooldown
                } else {
                    Log::warning("Rate limit hit for condenser_api.get_active_votes, delaying the request for follower: " . $usernameToCheck);
                }
            }
        } catch (\Throwable $th) {
            Log::warning("Process processUpvotes error: " . $th->getMessage());
        }
    }

    protected function processFollower($follower)
    {
        try {
            $votes = [];
            $isLimitted = false;

            $discordWebhookUrl = $follower->follower->discord_webhook_url;
            $username = $follower->follower->username;
            $userId = $follower->follower->id;
            $method = $follower->trailer_type;
            $limitMana = $method === 'downvote' ? $follower->follower->limit_downvote_mana : $follower->follower->limit_upvote_mana;
            $accountHistories = [];
            $voteOps = [];

            if ($this->canMakeRequest('condenser_api.get_accounts')) {
                $account = $this->makeHttpRequest([
                    'jsonrpc' => '2.0',
                    'method' => 'condenser_api.get_accounts',
                    'params' => [[$username]], //
                    'id' => 1,
                ]);
                // Process the response
                if (!empty($account)) {
                    $currentMana = $this->processAccountCurrentMana($account[0], $method);
                    $isLimitted = intval($currentMana) <= intval($limitMana);
                }
                $currentManaText = "Your mana (" . ($currentMana / 100) < ($limitMana / 100) . ") is low, can't process a vote.";

                if (!$isLimitted) {
                    $currentManaText = "Your mana (" . ($currentMana / 100) < ($limitMana / 100) . ") is high, can process a vote";
                    $accountWatcher = $follower->user->username;

                    if ($this->canMakeRequest('condenser_api.get_account_history')) {
                        // Process the response
                        $history = $this->getAccountHistory($accountWatcher);

                        if (in_array($follower->trailer_type, ['curation', 'downvote'])) {
                            $this->processUpvotes(
                                $history,
                                $username,
                                $follower->weight,
                                $follower->voting_type,
                                $limitMana,
                            );
                        }

                        if ($follower->trailer_type === 'upvote_post') {
                            $posts = $this->getAccountPost($accountWatcher);

                            foreach ($posts as $post) {
                                if ($post['author'] === $accountWatcher) {
                                    $votes[] = [
                                        'voter' => $username,
                                        'author' => $post['author'],
                                        'permlink' => $post['permlink'],
                                        'weight' => $follower->weight,
                                        'limitMana' => $limitMana,
                                        'method' => $method,
                                    ];

                                    $toVote = collect([
                                        'voter' => $username,
                                        'author' => $post['author'],
                                        'permlink' => $post['permlink'],
                                        'weight' => $follower->weight,
                                        'limitMana' => $limitMana,
                                        'method' => $method,
                                    ]);

                                    ProcessUpvoteJob::dispatch($toVote)->onQueue('voting');
                                }
                            }
                        }

                        if ($follower->trailer_type === 'upvote_comment') {
                            $userPosts = $this->getAccountPost($username);

                            foreach ($userPosts as $post) {
                                $replies = $this->getContentReplies($username, $post['permlink']);

                                if (count($replies)) {
                                    foreach ($replies as $reply) {
                                        if ($reply['author'] === $accountWatcher) {
                                            $votes[] = [
                                                'voter' => $username,
                                                'author' => $reply['author'],
                                                'permlink' => $reply['permlink'],
                                                'weight' => $follower->weight,
                                                'limitMana' => $limitMana,
                                                'method' => $method,
                                            ];

                                            $toVote = collect([
                                                'voter' => $username,
                                                'author' => $reply['author'],
                                                'permlink' => $reply['permlink'],
                                                'weight' => $follower->weight,
                                                'limitMana' => $limitMana,
                                                'method' => $method,
                                            ]);
                                            ProcessUpvoteJob::dispatch($toVote)->onQueue('voting');
                                        }
                                    }
                                }
                            }
                        }

                        // Cache::put('last_api_request_time.condenser_api.get_account_history', now(), 60); // 60 seconds cooldown
                    } else {
                        Log::warning("Rate limit hit for condenser_api.get_account_history, delaying the request for follower: " . $username);
                    }
                } else {
                    Log::warning("Mana is not enough");
                }

                $countData = count($votes ?? []);
                $displayData = json_encode($votes);

                if ($discordWebhookUrl && !$isLimitted) {
                    $logMessages = <<<LOG
                    ----------------------------------------------------------
                    $displayData
                    ----------------------------------------------------------
                    LOG;

                    $discordFields = [
                        [
                            'name' => 'Current Mana',
                            'value' => $currentMana / 100 . "% *(hive mana)*",
                            'inline' => false,
                        ],
                        [
                            'name' => 'Limit Mana',
                            'value' => $limitMana / 100 . "% *(Settings in auto.vote)*",
                            'inline' => false,
                        ],
                        [
                            'name' => 'Mana Status',
                            'value' => $currentManaText,
                            'inline' => false,
                        ],
                        [
                            "name" => "Total voted for this process",
                            "value" => $countData,
                            "inline" => false
                        ],
                    ];

                    SendDiscordNotificationJob::dispatch($userId, $discordFields, $logMessages)
                        ->onQueue('notification');
                }
                // Cache the timestamp of the request
                // Cache::put('last_api_request_time.condenser_api.get_accounts', now(), 60); // 180 seconds cooldown = 3 minutes
            } else {
                Log::warning("Rate limit hit for condenser_api.get_accounts, delaying the request for follower: " . $username);
            }

            // $this->updateFollowerProcessingStatus($follower->id, false);
        } catch (\Exception $e) {
            Log::error("Job failed for follower " . $follower->id . ": " . $e->getMessage());
            // $this->updateFollowerProcessingStatus($follower->id, false);
        }
    }

    private function updateFollowerProcessingStatus($followerId, $status)
    {
        Follower::where('id', $followerId)
            ->update(['is_being_processed' => $status]);
    }


    protected function canMakeRequest($name)
    {
        return !Cache::has('last_api_request_time.' . $name);
    }

    protected function makeHttpRequest($data)
    {
        // Replace with your actual HTTP request logic
        return Http::post('https://rpc.d.buzz/', $data)->json()['result'] ?? [];
    }

    protected function calculateVotingWeight($userWeightOption, $authorWeight, $votingType)
    {
        $convertHivePercentage = 10000; // 100%
        $percentage = $userWeightOption; // 1%
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

    protected function processAccountCurrentMana($account, $method)
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

        if ($method === 'downvote') {
            return $this->getDownvoteMana($account, $maxMana);
        } else {
            return $this->getUpvoteMana($account, $maxMana);
        }
    }

    public function getUpvoteMana($account, $maxMana)
    {
        $delta = time() - $account['voting_manabar']['last_update_time'];
        $current_mana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);
        $percentage = round($current_mana / $maxMana * 10000);

        if (!is_finite($percentage)) $percentage = 0;
        if ($percentage > 10000) $percentage = 10000;
        elseif ($percentage < 0) $percentage = 0;

        // $percent = number_format($percentage / 100, 2);

        return intval($percentage);
    }

    public function getDownvoteMana($account, $maxMana)
    {
        $currentPower = $account['downvote_manabar']['current_mana'];
        $lastUpdateTime = $account['downvote_manabar']['last_update_time'];
        $fullRechargeTime = 432000;

        $now = time();
        $secondsSinceUpdate = $now - $lastUpdateTime;

        $maxMana = // assign the value of maxMana here, same as in your JS code

            $currentDownvotePower = ($currentPower + $secondsSinceUpdate * ($maxMana / $fullRechargeTime)) / $maxMana;
        $currentDownvotePower = min($currentDownvotePower, 1); // Cap at 100%

        // Convert to percentage and format to 2 decimal places
        $downvotePowerPercent = number_format($currentDownvotePower * 100, 2);

        return intval($downvotePowerPercent);
    }
}
