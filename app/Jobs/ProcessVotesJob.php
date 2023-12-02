<?php

namespace App\Jobs;

use App\Models\Follower;
use App\Traits\DiscordTrait;
use Carbon\Carbon;
use Hive\Hive;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessVotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $followers;
    protected $postingPrivateKey;
    protected $hive;
    public $tries = 3;
    public $timeout = 120; // in seconds

    public function __construct($followers, $postingPrivateKey, Hive $hive)
    {
        $this->followers = $followers;
        $this->postingPrivateKey = $postingPrivateKey;
        $this->hive = $hive;
    }


    public function handle()
    {
        Log::info("Starting ProcessVotesJob for followers chunk: " . count($this->followers));
        // broadcastVotes logic here
        foreach ($this->followers as $follower) {
            // Database lock check
            DB::transaction(function () use ($follower) {
                $freshFollower = Follower::find($follower->id);

                if ($freshFollower->is_being_processed) {
                    return;
                }

                $freshFollower->is_being_processed = true;
                $freshFollower->save();

                try {
                    // Main job logic
                    Log::info("Process starting for " . $follower->follower->username);
                    $this->processFollower($freshFollower);
                    Log::info("Processing done for " . $follower->follower->username);

                    $freshFollower->is_being_processed = false;
                    $freshFollower->save();
                } catch (\Exception $e) {
                    Log::error("Job failed for follower " . $freshFollower->id . ": " . $e->getMessage());
                    $freshFollower->is_being_processed = false;
                    $freshFollower->save();
                }
            });
        };
        Log::info("ProcessVotesJob completed successfully");
    }

    protected function canMakeRequest()
    {
        return !Cache::has('last_api_request_time');
    }

    protected function makeHttpRequest($data)
    {
        // Replace with your actual HTTP request logic
        return Http::post('https://rpc.d.buzz/', $data)->json()['result'] ?? [];
    }

    protected function broadcastVote($vote, $postingPrivateKey)
    {
        $result = $this->hive->broadcast($postingPrivateKey, 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $vote->weight      // weight
        ]);

        Log::info('Voting result: ', $result);
    }

    protected function calculateVotingWeight($userWeightOption, $authorWeight, $votingType)
    {
        $percentage = $userWeightOption; // 1%
        $baseValue = $authorWeight / 100; // 13%
        $method = strtolower($votingType);
        $result = 0;

        if ($method === 'fixed') {
            $result = $percentage * 100;
        }

        if ($method === 'scaled') {
            $result = (($percentage / 100) * $baseValue) * 100 * 100;
        }

        return intval($result);
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
        $delta = time() - $account['voting_manabar']['last_update_time'];
        $current_mana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);
        $percentage = round($current_mana / $maxMana * 10000);

        if (!is_finite($percentage)) $percentage = 0;
        if ($percentage > 10000) $percentage = 10000;
        elseif ($percentage < 0) $percentage = 0;

        $percent = number_format($percentage / 100, 2);

        return $percent;
    }

    protected function processFollower($follower)
    {
        // Place your job's logic here
        $currentDateTime = Carbon::now('Asia/Manila');
        $newDateTime = $currentDateTime->addMinutes(70);
        $formattedTime = $newDateTime->format('Y-m-d H:i:s');
        $lastProcessedTxId = -1;
        $data = [];
        $isLimitted = false;

        $discordWebhookUrl = $follower->follower->discord_webhook_url;
        $username = $follower->follower->username;
        $userId = $follower->follower->id;
        $limitMana = $follower->follower->limit_power;
        $accountHistories = [];
        $voteOps = [];

        if ($this->canMakeRequest()) {
            $account = $this->makeHttpRequest([
                'jsonrpc' => '2.0',
                'method' => 'condenser_api.get_accounts',
                'params' => [[$username]], //
                'id' => 1,
            ]);
            // Process the response
            if (!empty($account)) {
                $currentMana = $this->processAccountCurrentMana($account[0]);
                $isLimitted = $currentMana < $limitMana;
            }

            $currentManaText = "Your mana ($currentMana < $limitMana) is low, can't process a vote.";


            if (!$isLimitted) {
                $currentManaText = "Your mana ($currentMana < $limitMana) is high, can process a vote";
                $accountWatcher = $follower->user->username;
                $method = $follower->voting_type;
                $userWeight = $follower->weight;

                if ($this->canMakeRequest()) {
                    // Process the response
                    $accountHistories = $this->makeHttpRequest([
                        'jsonrpc' => '2.0',
                        'method' => 'condenser_api.get_account_history',
                        'params' => [$accountWatcher, -1, 100], //
                        'id' => 1,
                    ]);

                    $voteOps = collect($accountHistories)
                        ->filter(function ($tx) use ($lastProcessedTxId) {
                            return $tx[0] > $lastProcessedTxId;
                        })
                        ->map(function ($tx) use (&$lastProcessedTxId) {
                            $lastProcessedTxId = $tx[0];
                            return $tx[1]['op'];
                        })
                        ->filter(function ($op) use ($accountWatcher) {
                            return $op[0] === 'vote' && $op[1]['voter'] === $accountWatcher;
                        });


                    foreach ($voteOps as $voteOp) {
                        $postAuthor = $voteOp[1]['author'];
                        $postPermlink = $voteOp[1]['permlink'];
                        $postWeight = $voteOp[1]['weight'];
                        $weight = $this->calculateVotingWeight($userWeight, $postWeight, $method);

                        $vote = [
                            'voter' => $username,
                            'author' => $postAuthor,
                            'permlink' => $postPermlink,
                            'weight' => $weight,
                        ];

                        // Example of an HTTP request with rate limiting
                        if ($this->canMakeRequest()) {
                            $activeVotes = $this->makeHttpRequest([
                                'jsonrpc' => '2.0',
                                'method' => 'condenser_api.get_active_votes',
                                'params' => [$postAuthor, $postPermlink],
                                'id' => 1,
                            ]);
                            // Process the response
                            $votes = collect($activeVotes)
                                ->contains(function ($vote) use ($username) {
                                    return $vote['voter'] === $username;
                                });

                            if (!$votes) {
                                $data[] = $vote;
                                // $this->broadcastVote((object)$vote, $this->postingPrivateKey);
                            }
                            // Cache the timestamp of the request
                            Cache::put('last_api_request_time.condenser_api.get_active_votes', now(), 60); // 180 seconds cooldown = 3 minutes
                        } else {
                            Log::warning("Rate limit hit condenser_api.get_active_votes, delaying the request for follower: " . $follower->id);
                        }
                    }
                    // Cache the timestamp of the request
                    Cache::put('last_api_request_time.condenser_api.get_account_history', now(), 60); // 60 seconds cooldown
                } else {
                    Log::warning("Rate limit hit for condenser_api.get_account_history, delaying the request for follower: " . $follower->id);
                }
            } else {
                Log::warning($currentManaText);
            }

            Log::info('Total accountHistories: ' . count($accountHistories ?? []));
            Log::info('Total voteOps: ' . count($voteOps ?? []));
            Log::info('Total votes: ' . count($data ?? []));
            Log::info('Voter: ' . $username);

            $countAccountHistories = count($accountHistories ?? []);
            $countVoteOps = count($voteOps ?? []);
            $countData = count($data ?? []);

            if ($discordWebhookUrl) {
                $logMessages = <<<LOG
                ----------------------------------------------------------
                LOG;

                $discordFields = [
                    [
                        'name' => 'Current Mana',
                        'value' => $currentMana . "% *(hive mana)*",
                        'inline' => false,
                    ],
                    [
                        'name' => 'Limit Mana',
                        'value' => $limitMana . "% *(Settings in auto.vote)*",
                        'inline' => false,
                    ],
                    [
                        'name' => 'Mana Status',
                        'value' => $currentManaText,
                        'inline' => false,
                    ],

                    [
                        "name" => "Fetched account history",
                        "value" => $countAccountHistories,
                        "inline" => false
                    ],
                    [
                        "name" => "Total voted",
                        "value" => $countVoteOps,
                        "inline" => false
                    ],
                    [
                        "name" => "Total voted for this process",
                        "value" => $countData,
                        "inline" => false
                    ],
                ];

                SendDiscordNotificationJob::dispatch($userId, $discordFields, $logMessages);
            }
            // Cache the timestamp of the request
            Cache::put('last_api_request_time.condenser_api.get_accounts', now(), 60); // 180 seconds cooldown = 3 minutes
        } else {
            Log::warning("Rate limit hit for condenser_api.get_accounts, delaying the request for follower: " . $follower->id);
        }
    }
}
