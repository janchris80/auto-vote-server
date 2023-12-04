<?php

namespace App\Jobs;

use App\Models\Vote;
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

    protected $hive;
    public $tries = 3;
    public $timeout = 120; // in seconds

    public function __construct(Hive $hive)
    {
        $this->hive = $hive;
    }

    public function handle(): void
    {
        $postingPrivateKey = config('hive.private_key.posting'); // Be cautious with private keys

        $votes = Vote::query()

            ->get();

        foreach($votes as $vote) {
            try {
                $this->broadcastVote($vote, $postingPrivateKey);
                $vote->delete();
            } catch (\Throwable $th) {
                throw $th;
            }
        }


    }

    protected function broadcastVote($vote, $postingPrivateKey)
    {
        $result = $this->hive->broadcast($postingPrivateKey, 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $vote->weight      // weight
        ]);

        Log::info('Voting result: ', $result);

        return $result;
    }
}
