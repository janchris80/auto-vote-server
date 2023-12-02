<?php

namespace App\Console\Commands\Broadcast;

use App\Jobs\ProcessVotesJob;
use App\Models\Follower;
use Hive\Hive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Vote extends Command
{
    protected $signature = 'broadcast:vote';
    protected $description = 'Vote';
    protected $hive;

    public function __construct(Hive $hive)
    {
        parent::__construct();
        $this->hive = $hive;
    }

    public function handle()
    {
        $startTime = microtime(true); // Start timer
        $postingPrivateKey = $this->hive->privateKeyFrom(config('hive.private_key.posting'));
        Follower::query()
            ->whereHas('follower', function ($query) {
                $query->where('enable', 1);
                // ->where('current_power', '<=', DB::raw('`users`.`limit_power`')); // remove due to not align in live data
            })
            ->with(['user', 'follower'])
            ->where('enable', '=', 1)
            ->chunk(100, function ($followers) use ($postingPrivateKey) {
                // dump($follower->toArray());
                // $this->broadcastVotes($follower, $postingPrivateKey);
                ProcessVotesJob::dispatch($followers, $postingPrivateKey, $this->hive);
            });

        $endTime = microtime(true); // End timer
        $duration = $endTime - $startTime; // Calculate duration

        Log::info("Total time taken: {$duration} seconds");
    }
}
