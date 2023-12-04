<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\ProcessVotesJob;
use App\Models\Follower;
use Hive\Helpers\PrivateKey;
use Illuminate\Console\Command;

class Vote extends Command
{
    protected $signature = 'broadcast:vote';
    protected $description = 'Vote';

    public function handle()
    {
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);
        Follower::query()
            ->whereHas('follower', function ($query) {
                $query->where('is_enable', 1);
                // ->where('current_power', '<=', DB::raw('`users`.`limit_power`')); // remove due to not align in live data
            })
            ->with(['user', 'follower'])
            ->where('enable', '=', 1)
            ->chunk(100, function ($followers) use ($postingPrivateKey) {
                // dump($follower->toArray());
                // $this->broadcastVotes($follower, $postingPrivateKey);
                ProcessVotesJob::dispatch($followers, $postingPrivateKey);
            });
    }
}
