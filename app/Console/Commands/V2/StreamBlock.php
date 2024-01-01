<?php

namespace App\Console\Commands\V2;

use App\Jobs\V2\ProcessUpvoteCommentsJob;
use App\Jobs\V2\ProcessUpvoteCuratorsJob;
use App\Jobs\V2\ProcessUpvotePostsJob;
use App\Models\Trailer;
use App\Models\UpvotePost;
use App\Traits\HelperTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class StreamBlock extends Command
{
    use HelperTrait;

    protected $signature = 'stream:block';
    protected $description = 'Stream block';

    public function handle()
    {
        $lastBlock = $this->getLastBlock();
        $this->isNewBlock($lastBlock);
        $this->processNewBlock($lastBlock);
    }

    protected function isNewBlock(&$lastBlock)
    {
        if ($lastBlock === 0) {
            $streamBlockNumber = $this->getDynamicGlobalProperties();
            $lastBlock = $streamBlockNumber['head_block_number'] ?? 0;
        } else {
            $lastBlock += 1;
        }
    }

    protected function processNewBlock($lastBlock)
    {
        $retryCount = 0;
        $maxRetries = 10;

        $streamBlockOperations = $this->retryFetchingBlockOperations($lastBlock, $retryCount, $maxRetries);

        if ($streamBlockOperations) {
            $operations = $this->pluckOperations($streamBlockOperations, $lastBlock);

            if (count($operations['comment'])) {
                ProcessUpvoteCommentsJob::dispatch($operations['comment'])->onQueue('comment');
            }
            if (count($operations['post'])) {
                ProcessUpvotePostsJob::dispatch($operations['post'])->onQueue('post');
            }
            if (count($operations['curation'])) {
                ProcessUpvoteCuratorsJob::dispatch($operations['curation'])->onQueue('curation');
            }
        }
    }

    protected function retryFetchingBlockOperations($lastBlock, &$retryCount, $maxRetries)
    {
        $streamBlockOperations = null;

        while (empty($streamBlockOperations) && $retryCount < $maxRetries) {
            $retryCount++;

            $streamBlockOperations = $this->getApiData('condenser_api.get_block', [$lastBlock]);
        }

        return $streamBlockOperations;
    }

    protected function pluckOperations($streamBlockOperations, $lastBlock)
    {
        $transactions = collect($streamBlockOperations['transactions'])
            ->pluck('operations.0')
            ->toArray();

        $operations['comment'] = collect($transactions)
            ->filter(function ($operation) {
                return $operation[0] === 'comment'
                    && $operation[1]['parent_author'] !== ''
                    && in_array($operation[1]['parent_author'], $this->fetchUpvoteCommentAuthors());
            })
            ->map(function ($comment) {
                return [
                    'parent_author' => $comment[1]['parent_author'],
                    'author' => $comment[1]['author'],
                    'permlink' => $comment[1]['permlink'],
                    'parent_permlink' => $comment[1]['parent_permlink'],
                ];
            });

        $operations['post'] = collect($transactions)
            ->filter(function ($operation) {
                $editRegex = '/^(@@+.+@@)/';
                return $operation[0] === 'comment'
                    && $operation[1]['parent_author'] === ''
                    && in_array($operation[1]['author'], $this->fetchUpvotePostAuthors())
                    && !preg_match($editRegex, $operation[1]['body']);
            })
            ->map(function ($post) {
                return [
                    'author' => $post[1]['author'],
                    'permlink' => $post[1]['permlink'],
                ];
            });

        $operations['curation'] = collect($transactions)
            ->filter(function ($operation) {
                $editRegex = '/^re-/';

                return $operation[0] === 'vote'
                    && $operation[1]['weight'] > 0
                    && $operation[1]['voter'] !== $operation[1]['author']
                    && in_array($operation[1]['voter'], $this->fetchUpvoteCurationFollowedAuthors())
                    && !preg_match($editRegex, $operation[1]['permlink']);
            })
            ->map(function ($post) {
                return $post[1];
            });

        // Update the value in the cache
        Cache::forever('last_block', $lastBlock);

        return $operations;
    }
}
