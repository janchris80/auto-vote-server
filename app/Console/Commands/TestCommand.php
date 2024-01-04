<?php

namespace App\Console\Commands;

use App\Models\Trailer;
use App\Traits\HelperTrait;
use Hive\Hive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TestCommand extends Command
{
    use HelperTrait;

    protected $signature = 'app:test';

    protected $description = 'Just for testing';

    public function handle()
    {
        $response = $this->getApiData('account_history_api.get_ops_in_block', [
            'block_num' => 80384995,
            'only_virtual' => false,
        ]);

        $collect = collect($response['ops'] ?? []);

        foreach ($collect as $item) {
            if (in_array($item['op']['type'], ['comment_operation', 'vote_operation'])) {
                dump($item['op']['type'], $item['op']['value']);
            }
        }
        die;

        dd($collect);
    }
}
