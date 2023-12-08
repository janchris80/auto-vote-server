<?php

namespace App\Jobs;

use App\Traits\DiscordTrait;
use Carbon\Carbon;
use Hive\Helpers\PrivateKey;
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
    public $tries = 3;
    public $timeout = 300; // in seconds

    public function __construct($followers)
    {;
        $this->followers = $followers;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);

        $startTime = microtime(true); // Start timer
        Log::info("Starting ProcessClaimRewardsJob for followers chunk: " . count($this->followers));
        // broadcastClaimReward logic here
        foreach ($this->followers as $follower) {
            try {
                $this->broadcastClaimReward($follower, $postingPrivateKey);
            } catch (\Exception $e) {
                Log::warning('Error claiming rewards: ' . $e->getMessage());
            }
        }

        Log::info("Job ProcessClaimRewardsJob successfully");
        Log::info("Total time taken: {" . microtime(true) - $startTime . "} seconds\n");
    }

    protected function broadcastClaimReward($follower, $postingPrivateKey)
    {
        $hive = new Hive();
        $username = $follower->username;
        $userId = $follower->id;
        $discordWebhookUrl = $follower->discord_webhook_url;

        $hasRewards = true;

        if ($this->canMakeRequest('claim.condenser_api.get_accounts')) {
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

                    $hive->broadcast(
                        $postingPrivateKey,
                        'claim_reward_balance',
                        array_values($opParams)
                    );
                }
            }

            if ($discordWebhookUrl) {
                $message = $hasRewards ? 'Rewards claimed successfully for' : 'No rewards to claim for';

                $logMessages = <<<LOG
                ----------------------------------------------------------
                $message **$username**
                ----------------------------------------------------------
                LOG;

                SendDiscordNotificationJob::dispatch($userId, [], $logMessages)->onQueue('notification');
            }

            // Cache the timestamp of the request
            Cache::put('last_api_request_time.claim.condenser_api.get_accounts', now(), 60); // 180 seconds cooldown = 3 minutes
        } else {
            Log::warning("Rate limit hit claim.condenser_api.get_accounts, delaying the request for follower: " . $follower->id);
        }
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
}
