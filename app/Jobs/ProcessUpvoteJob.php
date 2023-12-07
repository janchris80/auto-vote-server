<?php

namespace App\Jobs;

use App\Models\Vote;
use Hive\Helpers\PrivateKey;
use Hive\Hive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUpvoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $votes;
    public $tries = 3;
    public $timeout = 120; // in seconds

    public function __construct($votes)
    {
        $this->votes = $votes;
    }

    public function handle(): void
    {
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);

        foreach($this->votes as $vote) {
            try {
                Log::info('', [$vote]);
                // $this->broadcastVote($vote, $postingPrivateKey);
                sleep(1);
                unset($vote);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
    }

    protected function broadcastVote($vote, $postingPrivateKey)
    {
        $hive = new Hive();
        $result = $hive->broadcast($postingPrivateKey, 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $vote->weight      // weight
        ]);

        if (isset($result['trx_id'])) {
            $vote->is_voted = 1;
            $vote->save();
        }

        Log::info('Voting result: ', $result);

        return $result;
    }
}
