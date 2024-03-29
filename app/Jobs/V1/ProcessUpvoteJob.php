<?php

namespace App\Jobs\V1;

use App\Models\Follower;
use Illuminate\Bus\Batchable;
use App\Models\Vote;
use App\Models\VoteLog;
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
        $hive = $this->hive();
        $vote = (object)$this->votes->all();

        try {
            $hasEnoughResourceCredit = $this->hasEnoughResourceCredit($vote->voter);
            $rcLeft = intval($this->getResourceCredit() * 100);

            if ($hasEnoughResourceCredit) {
                $account = $this->getAccounts($vote->voter)->first();
                $hasEnoughMana = $this->hasEnoughMana($account, $vote->trailerType, $vote->limitMana);
                $manaLeft = $this->getCurrentMana();

                if ($hasEnoughMana) {
                    $this->broadcastVote($vote, $hive, $manaLeft, $rcLeft);
                }
            }

            unset($vote);
        } catch (\Throwable $th) {
            Log::error("ProcessUpvoteJob " . $th->getMessage());
        }
    }

    protected function broadcastVote($vote, $hive, $manaLeft, $rcLeft)
    {
        $weight = $vote->votingType === 'downvote' ? intval(-$vote->weight) : intval($vote->weight);
        $result = $hive->broadcast($this->privateKey(), 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $weight,           // weight
        ]);

        if (isset($result['trx_id'])) {
            Follower::where('id', $vote->followerId)
                ->update([
                    'last_voted_at' => now()
                ]);

            VoteLog::create([
                'voter' => $vote->voter,
                'author' => $vote->author,
                'permlink' => $vote->permlink,
                'author_weight' => $vote->weight,
                'voter_weight' => $vote->voterWeight,
                'mana_left' => $manaLeft,
                'rc_left' => $rcLeft,
                'trailer_type' => $vote->trailerType,
                'voting_type' => $vote->votingType,
                'limit_mana' => $vote->limitMana,
                'voted_at' => $vote->votedAt,
                'followed_author' => $vote->followedAuthor,
            ]);
        }
    }
}
