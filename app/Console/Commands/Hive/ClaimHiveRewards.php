<?php

namespace App\Console\Commands\Hive;

use App\Models\User;
use Hive\Helpers\PrivateKey;
use Hive\Hive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ClaimHiveRewards extends Command
{
    protected $signature = 'broadcast:claim-rewards';
    protected $description = 'Claim Hive blockchain rewards';

    public function handle()
    {
        $startTime = microtime(true); // Start timer
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);

        $user = User::query()
            ->where('claim_reward', 1)
            ->where('enable', 1)
            ->chunk(200, function ($follower) use ($postingPrivateKey) {
                $this->broadcastClaimReward($follower, $postingPrivateKey);
            });

        $endTime = microtime(true); // End timer
        $duration = $endTime - $startTime; // Calculate duration

        $this->info("Total time taken: {$duration} seconds\n");
    }

    protected function broadcastClaimReward($claimRewards, $postingPrivateKey)
    {
        $claimRewards->each(function ($claimReward) use ($postingPrivateKey) {
            $hive = new Hive();
            $username = $claimReward->username;

            try {
                $account = Http::post('https://rpc.d.buzz/', [
                    'jsonrpc' => '2.0',
                    'method' => 'condenser_api.get_accounts',
                    'params' => [[$username]],
                    'id' => 1,
                ])->json()['result'][0] ?? [];
                // $account = $hive->call('condenser_api', 'get_accounts', [[$username]]);
                // dump($account);
                $rewardHive = $account['reward_hive_balance'];
                $rewardHbd = $account['reward_hbd_balance'];
                $rewardVests = $account['reward_vesting_balance'];

                if ($rewardHive === '0.000 HIVE' && $rewardHbd === '0.000 HBD' && $rewardVests === '0.000000 VESTS') {
                    $this->info('No rewards to claim for ' . $username);
                    return;
                }

                $opParams = [
                    'account' => $username,
                    'reward_hive' => $rewardHive,
                    'reward_hbd' => $rewardHbd,
                    'reward_vests' => $rewardVests
                ];

                $hive->broadcast($postingPrivateKey, 'claim_reward_balance', array_values($opParams));
                $this->info('Rewards claimed successfully for ' . $username);
            } catch (\Exception $e) {
                $this->error('Error claiming rewards: ' . $e->getMessage());
            }
        });
    }
}
