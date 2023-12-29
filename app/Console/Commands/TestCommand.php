<?php

namespace App\Console\Commands;

use App\Models\Trailer;
use App\Traits\HelperTrait;
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
        $totalSize = Cache::get('data_size');
        $sizeInGB = $totalSize / (1024 * 1024);
        dd($sizeInGB);
        // $trailers = $this->fetchTrailers();
        // $justUpvoteComments = $this->filterUpvoteComments($trailers);
        // $lastBlock = $this->getLastBlock();
        // $this->isNewBlock($lastBlock);
        // $this->processNewBlock($lastBlock, $justUpvoteComments);
    }

    protected function fetchTrailers()
    {
        return Cache::remember('trailers', $this->fiveMinutesInSecond, function () {
            return Trailer::query()
                ->with(['user' => function ($query) {
                    return $query->select([
                        'id',
                        'username',
                    ]);
                }])
                ->select('user_id', 'id', 'trailer_type')
                ->get()
                ->map(function ($trailer) {
                    return [
                        'username' => $trailer['user']['username'],
                        'user_id' => $trailer['user_id'],
                        'trailer_type' => $trailer['trailer_type'],
                        'id' => $trailer['id'],
                    ];
                });
        });
    }

    protected function filterUpvoteComments($trailers)
    {
        return $trailers->filter(function ($trailer) {
            return $trailer['trailer_type'] === 'upvote_comment';
        });
    }

    protected function getLastBlock()
    {
        return Cache::rememberForever('last_block', function () {
            return 81410086; // Default value if not found in cache
        });
    }

    protected function isNewBlock(&$lastBlock)
    {
        if ($lastBlock === 0) {
            $streamBlockNumber = $this->getApiData('condenser_api.get_dynamic_global_properties', []);
            $lastBlock = $streamBlockNumber['head_block_number'] ?? 0;
        } else {
            $lastBlock += 1;
        }
    }

    protected function processNewBlock($lastBlock, $justUpvoteComments)
    {
        $retryCount = 0;
        $maxRetries = 10;

        Log::info('lastBlock: ' . $lastBlock);

        $streamBlockOperations = $this->retryFetchingBlockOperations($lastBlock, $retryCount, $maxRetries);

        if ($streamBlockOperations) {
            $this->processBlockOperations($streamBlockOperations, $justUpvoteComments, $lastBlock);
        }
    }

    protected function retryFetchingBlockOperations($lastBlock, &$retryCount, $maxRetries)
    {
        $streamBlockOperations = null;

        while (empty($streamBlockOperations) && $retryCount < $maxRetries) {
            $retryCount++;

            Log::warning("Retry $retryCount: Failed to fetch block operations. Retrying...");

            $streamBlockOperations = $this->getApiData('condenser_api.get_block', [$lastBlock]);
        }

        return $streamBlockOperations;
    }

    protected function processBlockOperations($streamBlockOperations, $justUpvoteComments, $lastBlock)
    {
        $operations = collect($streamBlockOperations['transactions'])
            ->pluck('operations.0')
            ->toArray();

        $comments = collect($operations)
            ->filter(function ($operation) {
                return $operation[0] === 'comment' && $operation[1]['parent_author'] !== '';
            })
            ->all();

        $filteredComments = collect($comments)
            ->filter(function ($comment) use ($justUpvoteComments) {
                return $justUpvoteComments->contains('username', $comment[1]['parent_author']);
            })
            ->map(function ($comment) {
                return [
                    'parent_author' => $comment[1]['parent_author'],
                    'author' => $comment[1]['author'],
                    'permlink' => $comment[1]['permlink'],
                    'parent_permlink' => $comment[1]['parent_permlink'],
                ];
            });

        if (count($filteredComments)) {
            Log::info('lastBlock: ' . $lastBlock, [$filteredComments]);
            // Upvote comments
            // UpvoteComment::dispatch($filteredComments)->onQueue('voting');
        }

        // Update the value in the cache
        Cache::forever('last_block', $lastBlock);
    }
}
