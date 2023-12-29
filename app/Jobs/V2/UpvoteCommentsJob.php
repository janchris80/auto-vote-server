<?php

namespace App\Jobs\V2;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpvoteCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $vote;

    public function __construct($vote)
    {
        $this->vote = $vote;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('UpvoteCommentsJob Voting', [$this->vote]);
    }
}
