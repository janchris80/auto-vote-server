<?php

namespace App\Console\Commands\V2;

use App\Traits\HelperTrait;
use Illuminate\Console\Command;

class UpdateCacheCommand extends Command
{
    use HelperTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-cache-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'just to update cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->getDynamicGlobalProperties();
        $this->fetchUpvotePostAuthors();
        $this->fetchUpvoteCurationFollowedAuthors();
    }
}
