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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessUpvoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $votes;
    public $timeout = 300; // in seconds

    public function __construct($votes)
    {
        $this->votes = $votes;
    }

    public function handle(): void
    {
        $hive = new Hive();
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);

        $vote = (object)$this->votes->all();

        try {
            $activeVotes = $this->getActiveVotes($vote->voter, $vote->permlink);
            $isVoted = $activeVotes->contains('voter', $vote->voter);

            if (!$isVoted) {
               $this->broadcastVote($vote, $postingPrivateKey, $hive);
            }

            unset($vote);

        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function getActiveVotes($author, $permlink)
    {
        $response = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_active_votes',
            'params' => [$author, $permlink],
            'id' => 1,
        ]);

        return collect($response);
    }

    protected function makeHttpRequest($data)
    {
        // Replace with your actual HTTP request logic
        return Http::post('https://rpc.d.buzz/', $data)->json()['result'] ?? [];
    }

    protected function broadcastVote($vote, $postingPrivateKey, $hive)
    {
        $weight = $vote->method === 'downvote' ? intval(-$vote->weight) : intval($vote->weight);
        $result = $hive->broadcast($postingPrivateKey, 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $weight,           // weight
        ]);

        if (isset($result['trx_id'])) {
            Vote::updateOrCreate(
                [
                    'voter' => $vote->voter,
                    'author' => $vote->author,
                    'permlink' => $vote->permlink,
                ],
                [
                    'weight' => $vote->weight,
                    'is_voted' => true,
                ]
            );
        }
    }
}
