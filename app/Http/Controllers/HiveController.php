<?php

namespace App\Http\Controllers;


use App\Http\Requests\SearchUsernameRequest;
use App\Models\Follower;
use App\Models\User;
use App\Traits\HttpResponses;
use Hive\Hive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HiveController extends Controller
{
    use HttpResponses;
    private $postingPrivateKey;

    public function accountHistory()
    {
        $accountWatcher = 'dbuzz';
        $accountHistories = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_account_history',
            'params' => [$accountWatcher, -1, 100],
            'id' => 1,
        ])->json()['result'] ?? [];

        $data = [];
        $lastProcessedTxId = -1;

        $voteOps = collect($accountHistories)
            ->filter(function ($tx) use ($lastProcessedTxId) {
                return $tx[0] > $lastProcessedTxId;
            })
            ->map(function ($tx) use (&$lastProcessedTxId) {
                $lastProcessedTxId = $tx[0];
                return $tx[1]['op'];
            })
            ->filter(function ($op) use ($accountWatcher) {
                return $op[0] === 'vote' && $op[1]['voter'] === $accountWatcher;
            });


        // return $voteOps;
        foreach ($voteOps as $voteOp) {
            $postAuthor = $voteOp[1]['author'];
            $postPermlink = $voteOp[1]['permlink'];
            $weight = $voteOp[1]['weight'];

            $activeVotes = Http::post('https://rpc.d.buzz/', [
                'jsonrpc' => '2.0',
                'method' => 'condenser_api.get_active_votes',
                'params' => [$postAuthor, $postPermlink],
                'id' => 1,
            ])->json()['result'] ?? [];

            $votes = collect($activeVotes)
                ->contains(function ($vote) {
                    return $vote['voter'] === 'iamjco';
                });

            if (!$votes) {
                // vote function
            }

            $data[] = $votes;
        }

        return $data;
    }

    public function votes()
    {
        // $broadcast = $this->hive->broadcast($this->postingPrivateKey, 'vote', [
        //     'iamjco', // voter
        //     'dbuzz', // author
        //     'rtp2ok1xh1fynvktczma7', // permalink
        //     5000 // weight
        // ]);

        // return $broadcast;
    }

    public function searchAccount(SearchUsernameRequest $request)
    {
        $request->validated();

        $username = $request->username;
        $userId = auth()->id();

        // Check if the username starts with '@', and remove it
        $username = ltrim($username, '@');

        $response = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'bridge.get_profile',
            'params' => [
                'account' => $username
            ],
            'id' => 1,
        ]);

        $data['hive_user'] = $response->json()['result'] ?? [];

        if (!empty($data['hive_user'])) {
            $user = User::query()
                ->withFollower($userId, $request->trailerType)
                ->updateOrCreate([
                    'username' => $username,
                ]);

            $data['user'] = $user;
        }

        return $this->success($data);
    }
}
