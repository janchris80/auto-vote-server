<?php

namespace App\Jobs\V1;

use App\Traits\DiscordTrait;
use App\Traits\HelperTrait;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DiscordTrait, HelperTrait;

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
        foreach ($this->followers as $follower) {
            try {
                $this->broadcastClaimReward($follower);
            } catch (\Exception $e) {
                Log::warning('Error claiming rewards: ' . $e->getMessage());
            }
        }
    }

    protected function broadcastClaimReward($follower)
    {
        $hive = $this->hive();
        $username = $follower->username;
        $account = $this->getAccounts($username)->first();

        // Process the response
        if (!empty($account)) {
            $rewardHive = $account['reward_hive_balance'];
            $rewardHbd = $account['reward_hbd_balance'];
            $rewardVests = $account['reward_vesting_balance'];

            if ($rewardHive !== '0.000 HIVE' && $rewardHbd !== '0.000 HBD' && $rewardVests !== '0.000000 VESTS') {
                $opParams = [
                    'account' => $username,
                    'reward_hive' => $rewardHive,
                    'reward_hbd' => $rewardHbd,
                    'reward_vests' => $rewardVests
                ];

                $hive->broadcast(
                    $this->privateKey(),
                    'claim_reward_balance',
                    array_values($opParams)
                );

                Log::info('Rewards claim for ' . $username);
            }
        }
    }
}
