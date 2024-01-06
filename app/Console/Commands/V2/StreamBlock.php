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
use Illuminate\Support\Facades\Log;

class StreamBlock extends Command
{
    use HelperTrait;

    protected $signature = 'stream:block';
    protected $description = 'Get transactions within the block';

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
                Log::info('operations ' . $lastBlock . ' comment', $operations['comment']->toArray());
                ProcessUpvoteCommentsJob::dispatch($operations['comment'])->onQueue('comment');
            }
            if (count($operations['post'])) {
                Log::info('operations ' . $lastBlock . ' post', $operations['post']->toArray());
                ProcessUpvotePostsJob::dispatch($operations['post'])->onQueue('post');
            }
            if (count($operations['curation'])) {
                Log::info('operations ' . $lastBlock . ' curation', $operations['curation']->toArray());
                ProcessUpvoteCuratorsJob::dispatch($operations['curation'])->onQueue('curation');
            }
            if (count($operations['downvote'])) {
                Log::info('operations ' . $lastBlock . ' downvote', $operations['downvote']->toArray());
                // Add later for downvote
            }

            // if (count($operations['comment']) || count($operations['post']) || count($operations['curation']) || count($operations['downvote'])) {
            // Log::info('operations', $operations);
            // }
            // Log::info('lastBlock: ' . $lastBlock);
        }
    }

    protected function retryFetchingBlockOperations($lastBlock, &$retryCount, $maxRetries)
    {
        $streamBlockOperations['ops'] = [];

        while (count($streamBlockOperations['ops'] ?? []) === 0 && $retryCount < $maxRetries) {
            $streamBlockOperations = $this->getApiData('account_history_api.get_ops_in_block', [
                'block_num' => $lastBlock,
                'only_virtual' => false,
            ]);

            $retryCount += 1;

            if (count($streamBlockOperations['ops'] ?? []) === 0) {
                usleep(500000); // 1,000,000 microsecond = 1 second, 500,000microsecond = 0.5 second
            }
        }

        return $streamBlockOperations['ops'] ?? [];
    }

    protected function pluckOperations($streamBlockOperations, $lastBlock)
    {
        $transactions = collect($streamBlockOperations)
            ->filter(function ($operation) {
                return in_array($operation['op']['type'], ['comment_operation', 'vote_operation']);
            });

        $operations['comment'] = collect($transactions)
            ->filter(function ($operation) {
                return $operation['op']['type'] === 'comment_operation'
                    && in_array($operation['op']['value']['parent_author'], $this->fetchUpvoteCommentAuthors())
                    && $operation['op']['value']['parent_author'] !== '';
            })
            ->map(function ($operation) {
                return [
                    'parent_author' => $operation['op']['value']['parent_author'],
                    'author' => $operation['op']['value']['author'],
                    'permlink' => $operation['op']['value']['permlink'],
                    'parent_permlink' => $operation['op']['value']['parent_permlink'],
                ];
            });

        $operations['post'] = collect($transactions)
            ->filter(function ($operation) {
                $editRegex = '/^(@@+.+@@)/';
                return $operation['op']['type'] === 'comment_operation'
                    && in_array($operation['op']['value']['author'], $this->fetchUpvotePostAuthors())
                    && $operation['op']['value']['parent_author'] === ''
                    && !preg_match($editRegex, $operation['op']['value']['body']);
            })
            ->map(function ($operation) {
                return [
                    'author' => $operation['op']['value']['author'],
                    'permlink' => $operation['op']['value']['permlink'],
                ];
            });

        $operations['curation'] = collect($transactions)
            ->filter(function ($operation) {
                $reRegex = '/^re-/';

                return $operation['op']['type'] === 'vote_operation'
                    && in_array($operation['op']['value']['voter'], $this->fetchUpvoteCurationFollowedAuthors())
                    && $operation['op']['value']['weight'] > 0
                    && $operation['op']['value']['voter'] !== $operation['op']['value']['author']
                    && !preg_match($reRegex, $operation['op']['value']['permlink']);
            })
            ->map(function ($post) {
                return $post['op']['value'];
            });

        $operations['downvote'] = collect($transactions)
            ->filter(function ($operation) {
                $reRegex = '/^re-/';

                return $operation['op']['type'] === 'vote_operation'
                    && in_array($operation['op']['value']['voter'], $this->fetchDownvoteFollowedAuthors())
                    && $operation['op']['value']['weight'] < 0
                    && $operation['op']['value']['voter'] !== $operation['op']['value']['author']
                    && !preg_match($reRegex, $operation['op']['value']['permlink']);
            })
            ->map(function ($post) {
                return $post['op']['value'];
            });

        // Update the value in the cache
        if (count($streamBlockOperations) > 0) {
            Cache::forever('last_block', $lastBlock);
        }

        return $operations;
    }
}
