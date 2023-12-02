<?php

namespace App\Jobs;

use App\Traits\DiscordTrait;
use Carbon\Carbon;
use Hive\Hive;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessClaimRewardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DiscordTrait;

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

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true); // Start timer
        Log::info("Starting ProcessClaimRewardsJob for followers chunk: " . count($this->followers));
        // broadcastClaimReward logic here
        foreach ($this->followers as $follower) {
            try {
                Log::info("Process starting for " . $follower->username);
                $this->broadcastClaimReward($follower);
                Log::info("Processing done for " . $follower->username);
            } catch (\Exception $e) {
                Log::warning('Error claiming rewards: ' . $e->getMessage());
            }
        }

        Log::info("Job ProcessClaimRewardsJob successfully");

        $endTime = microtime(true); // End timer
        $duration = $endTime - $startTime; // Calculate duration

        Log::info("Total time taken: {$duration} seconds\n");
    }

    protected function broadcastClaimReward($follower)
    {
        $username = $follower->username;
        $userId = $follower->id;
        $discordWebhookUrl = $follower->discord_webhook_url;

        $hasRewards = true;

        if ($this->canMakeRequest()) {
            $account = $this->makeHttpRequest([
                'jsonrpc' => '2.0',
                'method' => 'condenser_api.get_accounts',
                'params' => [[$username]],
                'id' => 1,
            ])[0];

            // Process the response
            if (!empty($account)) {
                $rewardHive = $account['reward_hive_balance'];
                $rewardHbd = $account['reward_hbd_balance'];
                $rewardVests = $account['reward_vesting_balance'];

                if ($rewardHive === '0.000 HIVE' && $rewardHbd === '0.000 HBD' && $rewardVests === '0.000000 VESTS') {
                    Log::info('No rewards to claim for ' . $username);
                    $hasRewards = false;
                } else {
                    $opParams = [
                        'account' => $username,
                        'reward_hive' => $rewardHive,
                        'reward_hbd' => $rewardHbd,
                        'reward_vests' => $rewardVests
                    ];

                    $this->hive->broadcast(
                        $this->postingPrivateKey,
                        'claim_reward_balance',
                        array_values($opParams)
                    );
                }
            }

            Log::info('Rewards claimed successfully for ' . $username);

            if ($discordWebhookUrl) {
                $message = $hasRewards ? 'Rewards claimed successfully for' : 'No rewards to claim for';

                $logMessages = <<<LOG
                ----------------------------------------------------------
                $message **$username**
                ----------------------------------------------------------
                LOG;

                SendDiscordNotificationJob::dispatch($userId, [], $logMessages);
            }

            // Cache the timestamp of the request
            Cache::put('last_api_request_time.condenser_api.get_accounts', now(), 60); // 180 seconds cooldown = 3 minutes
        } else {
            Log::warning("Rate limit hit condenser_api.get_accounts, delaying the request for follower: " . $follower->id);
        }
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
}
