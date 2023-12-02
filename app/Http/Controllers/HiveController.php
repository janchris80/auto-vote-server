<?php

namespace App\Http\Controllers;

use App\Http\Requests\BroadcastVoteRequest;
use App\Http\Requests\SearchUsernameRequest;
use App\Models\User;
use App\Traits\HttpResponses;
use Hive\Helpers\Transaction;
use Hive\Hive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Hive\PhpLib\Hive\Condenser as HiveCondenser;

class HiveController extends Controller
{
    use HttpResponses;
    private $postingPrivateKey;
    private $hive;

    public function __construct()
    {
        $this->hive = new Hive();
        $this->postingPrivateKey = $this->hive->privateKeyFrom(config('hive.private_key.posting'));
    }

    public function hive()
    {
        // default options - these are already configured
        $options = array(
            'rpcNodes' => [
                'https://rpc.d.buzz/',
                'https://api.hive.blog',
                'https://rpc.ecency.com/',
                'https://hive-api.3speak.tv/',
                'https://hived.privex.io',
                'https://anyx.io',
                'https://api.deathwing.me',
                'https://hived.emre.sh',
                'https://hive-api.arcange.eu',
                'https://api.openhive.network',
                'https://techcoderx.com',
                'https://hive.roelandp.nl',
                'https://api.c0ff33a.uk',
            ],
            'chainId' => 'beeab0de00000000000000000000000000000000000000000000000000000000',
            'timeout' => 7
        );
        // Will try the next node after 7 seconds of waiting for response
        // Or on a network failure

        $hive = new Hive($options);

        // $result = $hive->call('condenser_api.get_accounts', '[["mahdiyari"]]');
        // returns the result as an array
        // echo $result[0]['name']; // "mahdiyari"
        // echo $result[0]['hbd_balance']; // "123456.000 HBD"

        // 5Ji9X9tD2BXYesYz6PJAGZX1AERNHu4j4951Z91HFJHiYcwDcei
        // STM58uaAqDQwbPPJCu5D5nzV9f8JVbDbC3u6RV3fW6ziZLFZq6we4
        // 202cd5e0f7c32cd7601afb71b9228ea8b36df5dc654af7c6746dbcdd68ea7a9b09226f74ea06cf9c7b17cccd410709ab0ba9636377f27f20f667e0e4a1f9005912

        // $privateKey = $hive->privateKeyFrom('5Ji9X9tD2BXYesYz6PJAGZX1AERNHu4j4951Z91HFJHiYcwDcei');
        // $publicKey = $hive->publicKeyFrom('STM58uaAqDQwbPPJCu5D5nzV9f8JVbDbC3u6RV3fW6ziZLFZq6we4');

        // $hash = hash('sha256', 'Login using Hive');
        // $verify = $publicKey->verify($hash, '202cd5e0f7c32cd7601afb71b9228ea8b36df5dc654af7c6746dbcdd68ea7a9b09226f74ea06cf9c7b17cccd410709ab0ba9636377f27f20f667e0e4a1f9005912');

        // $beneficiaries = '[0, {"beneficiaries": [{"account": "dbuzz","weight": 10000}]}]';
        // $beneficiaries = json_decode($beneficiaries);

        // $result = $hive->broadcast(
        //     $privateKey,
        //     'comment_options',
        //     ['iamjc93', 'testing-php-hive-functions', '1000000.000 HBD', 10000, true, true, [$beneficiaries]]
        // );

        $response = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_account_history',
            'params' => ['dbuzz', -1, 100],
            'id' => 1,
        ]);

        // $response = Http::get('https://api.hive.blog/account_history_api', [
        //     'account' => 'dbuzz',
        //     'from' => -1,
        //     'limit' => 100,
        // ]);

        $result = $response->json();

        // You can then access the response body, headers, and status code like this:
        // $body = $response->body();
        // $headers = $response->headers();
        // $status = $response->status();

        // If you want to convert the JSON response to an array, you can use the json method:
        // $data = $response->json();


        return response()->json([
            // 'key1' => $privateKey,
            // 'key2' => $publicKey,
            // 'key3' => '5JFKbANFHfvGtyizMAENzqbScbC7dvsD27XBhhJ8XxFfUigAbbh',
            'result' => $result,
            // 'verify' => $verify,
            // 'response' => [
            // 'data' => $data,
            // 'body' => $body,
            // 'headers' => $headers,
            // 'status' => $status,
            // ]
        ]);
    }

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
        // $response = Http::post('https://rpc.d.buzz/', [
        //     'jsonrpc' => '2.0',
        //     'method' => 'condenser_api.get_active_votes',
        //     'params' => ['lindarey', '47g3iyz3s2v2131vy2anw6'],
        //     'id' => 1,
        // ]);

        // return $response->json();

        $broadcast = $this->hive->broadcast($this->postingPrivateKey, 'vote', [
            'iamjco', // voter
            'dbuzz', // author
            'rtp2ok1xh1fynvktczma7', // permalink
            5000 // weight
        ]);

        return $broadcast;
    }

    public function searchAccount(SearchUsernameRequest $request)
    {
        $request->validated();

        $username = $request->username;
        $response = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_accounts',
            'params' => [[$request->username]],
            'id' => 1,
        ]);

        // $response = Http::get("https://hive.blog/@$username.json");

        $data['hive_user'] = $response->json()['result'][0] ?? [];

        if (!empty($data)) {
            $user = User::query()
                ->with(['followers', 'curationTrailer', 'downvoteTrailer', 'followingsCurationCount', 'followingsDownvoteCount', 'followersCount'])
                ->updateOrCreate([
                    'username' => $username,
                ]);

            $data['user'] = $user;
        }

        return $this->success($data);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $config = [
            "debug" => false,
            "disableSsl" => false,
            "heNode" => "api.hive-engine.com/rpc",
            "hiveNode" => "anyx.io",
        ];

        // $hiveApi = new HiveCondenser($config);
        // $result = $hiveApi->findProposal(211); // Will return data about the proposal 211

        // return $result;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Extract data from the request
        // $signedTxData = $request->input('signtx.result');

        // // Check if the necessary data is present
        // if (!isset($signedTxData['ref_block_num'], $signedTxData['ref_block_prefix'], $signedTxData['expiration'], $signedTxData['operations'], $signedTxData['signatures'])) {
        //     return response()->json(['error' => 'Invalid signed transaction data']);
        // }

        // // Build the transaction array
        // $transaction = [
        //     'ref_block_num' => $signedTxData['ref_block_num'],
        //     'ref_block_prefix' => $signedTxData['ref_block_prefix'],
        //     'expiration' => $signedTxData['expiration'],
        //     'operations' => $signedTxData['operations'],
        //     'extensions' => $signedTxData['extensions'],
        //     'signatures' => $signedTxData['signatures'],
        // ];

        // Convert the transaction array to an object of type Hive\Helpers\Transaction
        // $hiveTransaction = new Transaction($transaction);
        // $hiveTransaction->setTrxId(4);


        // Use the Hive PHP library to submit the signed transaction
        $hive = new Hive();
        // $result = $hive->("5d7e8fbdc6a30f5ff54e25330c3bce2a0525bea0")->confirmed;
        // $result = $hive->broadcast('5Ji9X9tD2BXYesYz6PJAGZX1AERNHu4j4951Z91HFJHiYcwDcei', 'comment');

        // Handle the result as needed
        // if ($result['success']) {
        return response()->json(['message' => 'Post successfully broadcasted', []]);
        // } else {
        //     return response()->json(['error' => 'Failed to broadcast post']);
        // }
    }

    public function broadcastTransaction(Request $request)
    {
        // The signed transaction data received from the client-side JavaScript
        $signedTransaction = $request->input('signed_transaction'); // Adjust this based on your actual implementation

        try {
            // Hive API endpoint
            $apiEndpoint = 'https://api.hive.blog';

            // Make a POST request to broadcast the signed transaction
            $response = Http::post($apiEndpoint . '/rpc', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'condenser_api.broadcast_transaction',
                'params' => [$signedTransaction],
            ]);

            // Check the response
            $responseData = $response->json();

            if (!empty($responseData['result'])) {
                // Transaction successfully broadcasted
                return response()->json(['success' => true, 'message' => 'Transaction successfully broadcasted']);
            } else {
                // Error broadcasting the transaction
                return response()->json(['success' => false, 'message' => 'Error broadcasting transaction']);
            }
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
