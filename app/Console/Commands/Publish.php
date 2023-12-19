<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUpvoteJob;
use App\Jobs\SendDiscordNotificationJob;
use App\Models\Follower;
use App\Traits\HelperTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Publish extends Command
{
    use HelperTrait;

    protected $signature = 'publish:test';
    protected $description = 'Command description';

    public function handle()
    {
        Follower::query()
            ->whereHas('follower', function ($query) {
                $query->where('is_enable', '=', 1);
            })
            ->with(['user', 'follower'])
            ->where('is_enable', '=', 1)
            ->chunk(10, function ($followers) {
                $this->processFollower($followers);
            });
    }
}
