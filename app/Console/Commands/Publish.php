<?php

namespace App\Console\Commands;

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

        foreach($getBlockResult['transactions'] as $transaction) {
            $operations[] = $transaction['operations'];
        }

        // dump($operations);
        foreach($operations as $operation) {
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
