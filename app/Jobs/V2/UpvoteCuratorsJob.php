<?php

namespace App\Jobs\V2;

use App\Traits\HelperTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpvoteCuratorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

    public $vote;
    public function __construct($vote)
    {
        $this->vote = $vote;
    }


    public function handle(): void
    {
        Log::info('voting', [$this->vote, $this->getLastBlock()]);
    }
}
