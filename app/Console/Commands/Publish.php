<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUpvoteJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Publish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publish:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $this->processBlocks();

        // $history = $this->getAccountHistory('dbuzz');
        // $result = $this->processVotes($history, 'iamjco');

        // $toVote = collect([
        //     'voter' => 'iamjco',
        //     'author' => 'iamjco',
        //     'permlink' => '4csrbr3tjqpmif38buv7jp',
        //     'weight' => 1000,
        //     'limitMana' => 10000,
        //     'method' => 'curation',
        // ]);

        // ProcessUpvoteJob::dispatch($toVote)->onQueue('voting');

        // $data = date('Y-m-d\TH:i:s', strtotime('+' . 60 . ' Seconds'));
        // dd($data);

        // dd(unpack('V', hex2bin('04d1f344cfc5b0537f82d7af8c61af8d602f89de'), 4)[1]);

        // dd($toVote);

        // Log::info("", $result);

        $qweqwe = $this->checkResourceCredit('dbuzz');
        dump($qweqwe);
    }

    protected function makeHttpRequest($data)
    {
        // Replace with your actual HTTP request logic
        return Http::post('https://rpc.d.buzz/', $data)->json()['result'] ?? [];
    }

    public function checkResourceCredit($username)
    {
        $account = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'rc_api.find_rc_accounts',
            'params' => ['accounts' => [$username]], //
            'id' => 1,
        ]);

        $accountData = $account['rc_accounts'][0];
        $currentMana = (float) $accountData['rc_manabar']['current_mana'];
        $maxMana = (float) $accountData['max_rc'];

        // Calculate the percentage
        $percentage = ($currentMana / $maxMana) * 100;
        $percent = number_format($percentage, 2);

        return $percent > 5;
    }

    function getAccountHistory($username)
    {
        $response = Http::post('https://api.hive.blog', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_account_history',
            'params' => [$username, -1, 150, 1],
            'id' => 1,
        ])->json()['result'] ?? [];

        $voteOps = collect($response)
            ->filter(function ($tx) use ($username) {
                return $tx[1]['op'][1]['voter'] === $username;
            })
            ->map(function ($tx) {
                return $tx[1]['op'][1];
            });

        return $voteOps;
    }

    function getActiveVotes($author, $permlink)
    {
        $response = Http::post('https://api.hive.blog', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_active_votes',
            'params' => [$author, $permlink],
            'id' => 1,
        ])->json()['result'] ?? [];

        return collect($response);
    }

    function processVotes($transactions, $usernameToCheck)
    {
        $votes = [];
        $voted = [];

        echo count($transactions);

        foreach ($transactions as $tx) {
            $activeVotes = $this->getActiveVotes($tx['author'], $tx['permlink']);

            $isVoted = $activeVotes->contains('voter', $usernameToCheck);

            if (!$isVoted) {
                $tx['voter'] = $usernameToCheck;
                $votes[] = $tx;
            }
        }

        dump($votes);

        return ['votes' => $votes, 'voted' => $voted];
    }

    public function processBlocks()
    {
        $result = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_dynamic_global_properties',
            'params' => [], //
            'id' => 1,
        ])->json()['result'];

        $headBlockNumber = $result['head_block_number'];
        $lastBlock = 0;

        if ($result && $headBlockNumber) {
            if ($headBlockNumber > $lastBlock) {
                $lastBlock = $headBlockNumber;
            }
        }

        $getBlockResult = $this->getBlock($lastBlock);
        $operations = [];

        foreach ($getBlockResult['transactions'] as $transaction) {
            $operations[] = $transaction['operations'];
        }

        // dump($operations);
        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op[0] === 'vote') {
                // Log::debug('Result', [$op]);
                dump($op);
                // Log::debug('Result', [$op[1]['parent_author'], $op[1]['author'], $op[1]['permlink'], $op[1]['parent_permlink']]);
            }
            // if ($op[0] === 'comment' && $op[1]['parent_author'] !== '') {
            //     Log::debug('Result', [$op[1]['parent_author'], $op[1]['author'], $op[1]['permlink'], $op[1]['parent_permlink']]);
            // }
        }

        // dump($getBlockResult);
    }

    public function getBlock($headBlockNumber)
    {
        $result = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_block',
            'params' => [$headBlockNumber], //
            'id' => 1,
        ])->json()['result'];

        return $result;
    }
}
