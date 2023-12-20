<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use App\Models\Vote;
use App\Traits\HelperTrait;
use Hive\Helpers\PrivateKey;
use Hive\Hive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUpvoteJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

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
            $hasEnoughResourceCredit = $this->hasEnoughResourceCredit($vote->voter);

            if ($hasEnoughResourceCredit) {
                $account = $this->getAccounts($vote->voter)->first();
                $hasEnoughMana = $this->hasEnoughMana($account, $vote->trailerType, $vote->limitMana);

                if ($hasEnoughMana) {
                    $this->broadcastVote($vote, $postingPrivateKey, $hive);

                }
            }

            unset($vote);
        } catch (\Throwable $th) {
            Log::error("ProcessUpvoteJob " . $th->getMessage());
        }
    }

    protected function broadcastVote($vote, $postingPrivateKey, $hive)
    {
        $weight = $vote->votingType === 'downvote' ? intval(-$vote->weight) : intval($vote->weight);
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
