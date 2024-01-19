<?php

namespace App\Jobs\V2;

use App\Models\UpvotedComment;
use App\Traits\HelperTrait;
use Hive\Hive;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VotingJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

    public $vote;

    public function __construct($vote)
    {
        $this->vote = $vote;
    }

    public function handle(): void
    {
        $hive = $this->hive();
        $vote = $this->vote;

        $result = $hive->broadcast($this->privateKey(), 'vote', [
            $vote['voter'],      // voter
            $vote['author'],     // author
            $vote['permlink'],   // permlink
            $vote['weight'],     // weight
        ]);

        Log::info('result of voter ' . $vote['voter'] . ' ' . $vote['trailer_type'], [$result, $vote]);

        if (isset($result['trx_id'])) {
            if (isset($vote['trailer_type']) && $vote['trailer_type'] === 'upvote_comment') {
                UpvotedComment::create($vote);
            }
        }
    }
}
