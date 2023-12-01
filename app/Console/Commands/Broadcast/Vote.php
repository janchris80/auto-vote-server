<?php

namespace App\Console\Commands\Broadcast;

use App\Models\Follower;
use Carbon\Carbon;
use Hive\Hive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
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
            ->whereHas('follower', function($query) {
                $query->where('enable', 1);
            })
            ->with(['user', 'follower'])
            ->where('enable', '=', 1)
            ->chunk(200, function ($follower) use ($postingPrivateKey) {
                // dump($follower->toArray());
                $this->broadcastVotes($follower, $postingPrivateKey);
            });

        $endTime = microtime(true); // End timer
        $duration = $endTime - $startTime; // Calculate duration

        Log::info("Total time taken: {$duration} seconds");
        $this->info("Total time taken: {$duration} seconds"); // Output the duration
    }

    protected function broadcastVotes($votes, $postingPrivateKey)
    {
        $votes->each(function ($vote) use ($postingPrivateKey) {
            $currentDateTime = Carbon::now('Asia/Manila');
            $newDateTime = $currentDateTime->addMinutes(70);
            $formattedTime = $newDateTime->format('Y-m-d H:i:s');
            $lastProcessedTxId = -1;
            $data = [];

            $username = $vote->follower->username;
            $accountWatcher = $vote->user->username;
            $method = $vote->voting_type;
            $userWeight = $vote->weigth;

            $startTimeget_account_history = microtime(true); // Start timer
            $accountHistories = Http::post('https://rpc.d.buzz/', [
                'jsonrpc' => '2.0',
                'method' => 'condenser_api.get_account_history',
                'params' => [$accountWatcher, -1, 100], //
                'id' => 1,
            ])->json()['result'] ?? [];

            $entTimeget_account_history = microtime(true);
            $durationget_account_history = $startTimeget_account_history - $entTimeget_account_history; // Calculate duration
            Log::info("Total startTimeget_account_history taken: {$durationget_account_history} seconds.");
            dump("Total startTimeget_account_history taken: {$durationget_account_history} seconds. $startTimeget_account_history - $entTimeget_account_history");

            $voteOps = collect($accountHistories)
                ->filter(function ($tx) use ($lastProcessedTxId) {
                    return $tx[0] > $lastProcessedTxId;
                })
                ->map(function ($tx) use (&$lastProcessedTxId) {
                    $lastProcessedTxId = $tx[0];
                    return $tx[1]['op'];
                })
                ->filter(function ($op) use ($username) {
                    return $op[0] === 'vote' && $op[1]['voter'] === $username;
                });


            foreach ($voteOps as $voteOp) {
                $postAuthor = $voteOp[1]['author'];
                $postPermlink = $voteOp[1]['permlink'];
                $postWeight = $voteOp[1]['weight'];

                $weight = intval($this->calculateVotingWeight($userWeight, $postWeight, $method));

                $vote = [
                    'voter' => $username,
                    'author' => $postAuthor,
                    'permlink' => $postPermlink,
                    'weight' => $weight,
                ];

                $startTimeget_active_votes = microtime(true); // Start timer
                $activeVotes = Http::post('https://rpc.d.buzz/', [
                    'jsonrpc' => '2.0',
                    'method' => 'condenser_api.get_active_votes',
                    'params' => [$postAuthor, $postPermlink],
                    'id' => 1,
                ])->json()['result'] ?? [];
                $endTimeget_active_votes = microtime(true); // Start timer
                $durationget_active_votes = $startTimeget_active_votes - $endTimeget_active_votes;
                Log::info("Total durationget_active_votes taken: {$durationget_active_votes} seconds");
                dump("Total durationget_active_votes taken: {$durationget_active_votes} seconds. $startTimeget_active_votes - $endTimeget_active_votes");

                $votes = collect($activeVotes)
                    ->contains(function ($vote) use ($username) {
                        return $vote['voter'] === $username;
                    });


                if (!$votes) {
                    $data[] = $vote;
                    // vote function
                    // $this->broadcastVote((object)$vote, $postingPrivateKey);
                }
            }

            $this->info('total accountHistories: ' . count($accountHistories));
            $this->info('total voteOps: ' . count($voteOps));
            $this->info('total votes: ' . count($data));
        });
    }

    protected function broadcastVote($vote, $postingPrivateKey)
    {
        $result = $this->hive->broadcast($postingPrivateKey, 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $vote->weight      // weight
        ]);

        Log::debug('voting', $result);
    }

    protected function calculateVotingWeight($userWeightOption, $authorWeight, $votingType)
    {
        // $userWeightOption 0 to 100
        if ($votingType === 'fixed') {
            return $userWeightOption * 100;
        }

        if ($votingType === 'scaled') {
            return (($userWeightOption * 100) * ($authorWeight / 100));
        }
    }
}
