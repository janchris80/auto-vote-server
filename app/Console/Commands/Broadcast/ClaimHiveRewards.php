<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\ProcessClaimRewardsJob;
use App\Models\User;
use Hive\Helpers\PrivateKey;
use Hive\Hive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClaimHiveRewards extends Command
{
    protected $signature = 'broadcast:claim-rewards';
    protected $description = 'Claim Hive blockchain rewards';
    protected $hive;

    public function __construct(Hive $hive)
    {
        parent::__construct();
        $this->hive = $hive;
    }

    public function handle()
    {

        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);

        User::query()
            ->where('is_auto_claim_reward', 1)
            ->where('is_enable', 1)
            ->chunk(100, function ($followers) use ($postingPrivateKey) {
                // $this->broadcastClaimReward($followers, $postingPrivateKey);
                ProcessClaimRewardsJob::dispatch($followers, $postingPrivateKey, $this->hive);
            });
    }
}
