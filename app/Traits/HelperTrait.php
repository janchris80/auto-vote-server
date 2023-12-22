<?php

namespace App\Traits;

use App\Jobs\ProcessUpvoteJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

trait HelperTrait
{
    public $resourceCredit = 0;
    public $currentMana = 0;
    public $fiveDaysInSeconds = 432000; // seconds
    public $downvoteManaRatio  = 0.25;

    public function canMakeRequest($name)
    {
        return !Cache::has('last_api_request_time.' . $name);
    }

    /**
     * Make a POST request to the Hive API.
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function getApiData(string $method, array $params): array
    {
        try {
            $response = Http::post(config('hive.api_url_node'), [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ]);

            // Decode and return the JSON response
            return $response->json()['result'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error in getApiData ' . $method . ': ' . $e->getMessage(), ['trace' => $e->getTrace()]);
            return [];
        }
    }

    /**
     * Get posts for a specific Hive account.
     *
     * @param string $username
     * @return Collection
     */
    public function getAccountPosts(string $username): Collection
    {
        $response = $this->getApiData('bridge.get_account_posts', [
            'sort' => 'posts',
            'account' => $username,
            'limit' => config('hive.account_posts_limit'),
        ]);

        return collect($response);
    }

    /**
     * Get account information for one or more Hive accounts.
     *
     * @param string|array $username
     * @param bool $delayedVotesActive
     * @return Collection
     */
    public function getAccounts(string | array $username, bool $delayedVotesActive = false): Collection
    {
        // Check if user is 'string' or ['string', 'string2']
        $usernames = is_array($username) ? $username : [$username];

        $response = $this->getApiData('condenser_api.get_accounts', [$usernames, $delayedVotesActive]);

        return collect($response);
    }

    public function getAccountHistory($username): Collection
    {
        $response = $this->getApiData('account_history_api.get_account_history', [
            "account" => $username,
            "start" => -1,
            "limit" => config('hive.account_history_limit'),
            "include_reversible" => true,
            "operation_filter_low" => 1
        ]);

        return collect($response['history']);
    }

    public function getVoteAccountHistory($username): Collection
    {
        return $this->getAccountHistory($username)
            ->filter(function ($transaction) use ($username) {
                return $transaction[1]['op']['value']['voter'] === $username;
            })
            ->map(function ($transaction) {
                return [
                    'id' => $transaction[0],
                    'timestamp' => $transaction[1]['timestamp'],
                    'voter' => $transaction[1]['op']['value']['voter'],
                    'author' => $transaction[1]['op']['value']['author'],
                    'weight' => $transaction[1]['op']['value']['weight'],
                    'permlink' => $transaction[1]['op']['value']['permlink'],
                ];
            });
    }


    public function getContentReplies($username, $permlink): Collection
    {
        $response = $this->getApiData('condenser_api.get_content_replies', [$username, $permlink]);

        return collect($response);
    }

    public function getActiveVotes($author, $permlink): Collection
    {
        $response = $this->getApiData('condenser_api.get_active_votes', [$author, $permlink]);

        return collect($response);
    }

    public function getResourceAccounts($username): Collection
    {
        // check if user is 'string' or ['string', 'string2']
        $usernames = is_array($username) ? $username : [$username];

        $response = $this->getApiData('rc_api.find_rc_accounts', ['accounts' => $usernames]);

        return collect($response['rc_accounts']);
    }

    public function hasVote(string $voter, string $author, string $permlink): bool
    {
        $activeVotes = $this->getActiveVotes($author, $permlink);
        return $activeVotes->contains('voter', $voter);
    }

    public function getResourceCredit(): float
    {
        return $this->resourceCredit;
    }

    public function getCurrentMana(): int
    {
        return $this->currentMana;
    }

    public function hasEnoughResourceCredit($voter, $minimumPercentage = 5): bool
    {
        $account = $this->getResourceAccounts($voter)
            ->filter(function ($tx) use ($voter) {
                return $tx['account'] === $voter;
            })
            ->first();

        $percent = $this->calculateResourceCreditsPercentage($account);

        $this->resourceCredit = $percent;

        return $percent >= ($minimumPercentage ?? config('hive.resource_credit_limit'));
    }

    public function hasEnoughMana($account, $trailerType, $limitMana): bool
    {
        $currentMana = $this->calculateAccountMana($account, $trailerType);
        $this->currentMana = $currentMana;
        return $currentMana > $limitMana;
    }

    public function calculateResourceCreditsPercentage($data): float
    {
        // Current unix timestamp in seconds
        $currentTimestamp = time();

        // Initialize result array
        $result = [
            'resourceCreditsPercent' => 0,
            'resourceCreditsWaitTime' => 0,
        ];

        // Extract data from the input
        $maxResourceCredits = floatval($data['max_rc']);
        $lastUpdateTime = intval($data['rc_manabar']['last_update_time']);
        $currentMana = floatval($data['rc_manabar']['current_mana']);

        // Calculate time elapsed since last update
        $elapsedTime = $currentTimestamp - $lastUpdateTime;

        // Calculate current resource credits
        $calculatedResourceCredits = $currentMana + ($elapsedTime * $maxResourceCredits) / $this->fiveDaysInSeconds;

        // Ensure calculated resource credits do not exceed the maximum
        if ($calculatedResourceCredits > $maxResourceCredits) {
            $calculatedResourceCredits = $maxResourceCredits;
        }

        // Calculate resource credits percentage
        $result['resourceCreditsPercent'] = round((100 * $calculatedResourceCredits) / $maxResourceCredits);

        // Calculate resource credits wait time
        // $result['resourceCreditsWaitTime'] = ((100 - $result['resourceCreditsPercent']) * $this->fiveDaysInSeconds) / 100;

        // Return the result resourceCreditsPercent
        return $result['resourceCreditsPercent'];
    }

    public function calculateAccountMana($account, $trailerType): int
    {
        // Extracting and processing account details
        $delegated = (int)str_replace('VESTS', '', $account['delegated_vesting_shares']);
        $received = (int)str_replace('VESTS', '', $account['received_vesting_shares']);
        $vesting = (int)str_replace('VESTS', '', $account['vesting_shares']);
        $withdrawRate = 0;

        // Calculate withdraw rate if applicable
        if ((int)str_replace('VESTS', '', $account['vesting_withdraw_rate']) > 0) {
            $withdrawRate = min(
                (int)str_replace('VESTS', '', $account['vesting_withdraw_rate']),
                (int)(($account['to_withdraw'] - $account['withdrawn']) / 1000000)
            );
        }

        // Calculate total vest and max mana
        $totalVest = $vesting + $received - $delegated - $withdrawRate;
        $maxMana = $totalVest * pow(10, 6);
        $maxManaDown = $maxMana * $this->downvoteManaRatio;

        // Determine manabar type based on trailer type
        $manabarType = $trailerType === 'downvote' ? 'downvote_manabar' : 'voting_manabar';
        $rate = $trailerType === 'downvote' ? $maxManaDown : $maxMana;

        // Calculate current mana and percentage
        $timeDifference = time() - $account[$manabarType]['last_update_time'];
        $timeAdjustedRate = $timeDifference * $rate;

        $currentMana = $account[$manabarType]['current_mana'] + ($timeAdjustedRate / $this->fiveDaysInSeconds);
        $percentage = round($currentMana / ($trailerType === 'downvote' ? $maxManaDown : $maxMana) * 10000);

        // Ensure percentage is within valid range
        if (!is_finite($percentage)) {
            $percentage = 0;
        }

        if ($percentage > 10000) {
            $percentage = 10000;
        } elseif ($percentage < 0) {
            $percentage = 0;
        }

        return (int)$percentage;
    }


    public function calculateVotingWeight(int $voterWeight, int $authorWeight, string $votingType): int
    {
        $convertHivePercentage = 10000; // 100%
        $votingType = strtolower($votingType); // fixed or scaled

        // Validate votingType
        if ($votingType !== 'fixed' && $votingType !== 'scaled') {
            throw new InvalidArgumentException('Invalid voting type. Only "fixed" or "scaled" are allowed.');
        }

        switch ($votingType) {
            case 'fixed':
                $result = $voterWeight;
                break;

            case 'scaled':
                $result = ($voterWeight / $convertHivePercentage) * $authorWeight;
                break;

            default:
                $result = 0;
        }

        return (int) $result;
    }

    public function processBatchVotingJob(array $jobs)
    {
        Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // All jobs completed successfully...
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // First batch job failure detected...
                Log::error('Error in processBatchVotingJob: ' . $e->getMessage(), ['Batch ID' => $batch->id, 'trace' => $e->getTrace()]);
            })
            ->finally(function (Batch $batch) {
                // The batch has finished executing...
            })
            ->allowFailures()
            ->onQueue('voting')
            ->name('voting')
            ->dispatch();
    }
}
