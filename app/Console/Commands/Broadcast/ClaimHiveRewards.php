<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\ProcessClaimRewardsJob;
use App\Models\User;
use Hive\Helpers\PrivateKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
