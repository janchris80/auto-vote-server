<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UpvoteComment;
use App\Models\UpvoteCurator;
use App\Models\UpvotedComment;
use App\Models\UpvotePost;
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
        $lastBlock = $this->getLastBlock();

        dump($lastBlock);
    }
}
