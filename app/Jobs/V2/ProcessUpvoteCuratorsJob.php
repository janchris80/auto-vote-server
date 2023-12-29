<?php

namespace App\Jobs\V2;

use App\Traits\HelperTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProcessUpvoteCuratorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HelperTrait;

    public $operations;
    public Collection $jobs;

    public function __construct($operations)
    {
        $this->operations = $operations;
        $this->jobs = collect();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            foreach ($this->operations as $operation) {
                // $this->processCommentBlockOperations(
                //     $operation['parent_author'],
                //     $operation['author'],
                //     $operation['permlink'],
                //     $operation['parent_permlink'],
                // );
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
