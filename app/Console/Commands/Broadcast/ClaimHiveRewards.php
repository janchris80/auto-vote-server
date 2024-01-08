<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\V1\ProcessClaimRewardsJob;
use App\Models\User;
use Illuminate\Console\Command;

class ClaimHiveRewards extends Command
{
    protected $signature = 'broadcast:claim-rewards';
    protected $description = 'Claim Hive blockchain rewards';


    public function handle()
    {
        User::query()
            ->where('is_auto_claim_reward', 1)
            ->where('is_enable', 1)
            ->chunk(100, function ($followers) {
                // $this->broadcastClaimReward($followers, $postingPrivateKey);
                ProcessClaimRewardsJob::dispatch($followers)->onQueue('claim-rewards');
            });
    }
}
