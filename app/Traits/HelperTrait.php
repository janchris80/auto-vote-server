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
    public function canMakeRequest($name)
    {
        return !Cache::has('last_api_request_time.' . $name);
    }

    public function getApiData(string $method, $params)
    {
        $response = Http::post(config('hive.api_url_node'), [
            'jsonrpc' => '2.0',
            'method' => (string) $method,
            'params' => $params,
            'id' => 1,
        ]);

        // Decode and return the JSON response
        return $response->json()['result'] ?? [];
    }

    public function getAccountPost($username): Collection
    {
        $response = $this->getApiData('bridge.get_account_posts', [
            "sort" => "posts",
            "account" => $username,
            "limit" => config('hive.account_posts_limit'),
        ]);

        return collect($response);
    }

    public function getAccounts(string | array $username, $delayedVotesActive = false): Collection
    {
        // check if user is 'string' or ['string', 'string2']
        $usernames = is_array($username) ? $username : [$username];

        $response = $this->getApiData('database_api.find_accounts', [
            'accounts' => $usernames,
            'delayed_votes_active' => $delayedVotesActive
        ]);

        return collect($response['accounts']);
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
            ->filter(function ($tx) use ($username) {
                return $tx[1]['op']['value']['voter'] === $username;
            })
            ->map(function ($tx) {
                return [
                    'id' => $tx[0],
                    'timestamp' => $tx[1]['timestamp'],
                    'voter' => $tx[1]['op']['value']['voter'],
                    'author' => $tx[1]['op']['value']['author'],
                    'weight' => $tx[1]['op']['value']['weight'],
                    'permlink' => $tx[1]['op']['value']['permlink'],
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

    public function hasEnoughResourceCredit($voter, $minimumPercentage = 5): bool
    {
        $account = $this->getResourceAccounts($voter)
            ->filter(function ($tx) use ($voter) {
                return $tx['account'] === $voter;
            })
            ->first();

        $percent = $this->calculateResourceCreditsPercentage($account);

        return $percent >= ($minimumPercentage ?? config('hive.resource_credit_limit'));
    }

    public function hasEnoughMana($account, $trailerType, $limitMana): bool
    {
        $currentMana = $this->calculateAccountMana($account, $trailerType);
        return $currentMana > $limitMana;
    }

    public function calculateResourceCreditsPercentage($data): float
    {
        // Constants
        $fiveDaysInSeconds = 432000;

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
        $calculatedResourceCredits = $currentMana + ($elapsedTime * $maxResourceCredits) / $fiveDaysInSeconds;

        // Ensure calculated resource credits do not exceed the maximum
        if ($calculatedResourceCredits > $maxResourceCredits) {
            $calculatedResourceCredits = $maxResourceCredits;
        }

        // Calculate resource credits percentage
        $result['resourceCreditsPercent'] = round((100 * $calculatedResourceCredits) / $maxResourceCredits);

        // Calculate resource credits wait time
        // $result['resourceCreditsWaitTime'] = ((100 - $result['resourceCreditsPercent']) * $fiveDaysInSeconds) / 100;

        // Return the result
        return $result['resourceCreditsPercent'];
    }

    public function calculateAccountMana($account, $trailerType): int
    {
        // Extracting and processing account details
        $delegated = floatval(str_replace('VESTS', '', $account['delegated_vesting_shares']));
        $received = floatval(str_replace('VESTS', '', $account['received_vesting_shares']));
        $vesting = floatval(str_replace('VESTS', '', $account['vesting_shares']));
        $withdrawRate = 0;

        if (intval(str_replace('VESTS', '', $account['vesting_withdraw_rate'])) > 0) {
            $withdrawRate = min(
                intval(str_replace('VESTS', '', $account['vesting_withdraw_rate'])),
                intval(($account['to_withdraw'] - $account['withdrawn']) / 1000000)
            );
        }

        $totalvest = $vesting + $received - $delegated - $withdrawRate;
        $maxMana = $totalvest * pow(10, 6);
        $maxManaDown = $maxMana * 0.25;

        if ($trailerType === 'downvote') {
            $deltaDown = time() - $account['downvote_manabar']['last_update_time'];
            $currentManaDown = $account['downvote_manabar']['current_mana'] + ($deltaDown * $maxManaDown / 432000);
            $percentageDown = round($currentManaDown / $maxManaDown * 10000);

            if (!is_finite($percentageDown)) $percentageDown = 0;
            if ($percentageDown > 10000) $percentageDown = 10000;
            elseif ($percentageDown < 0) $percentageDown = 0;

            $downvotePower = intval($percentageDown);

            return $downvotePower;
        } else {
            $delta = time() - $account['voting_manabar']['last_update_time'];
            $currentMana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);
            $percentage = round($currentMana / $maxMana * 10000);

            if (!is_finite($percentage)) $percentage = 0;
            if ($percentage > 10000) $percentage = 10000;
            elseif ($percentage < 0) $percentage = 0;

            $upvotePower = intval($percentage);

            return $upvotePower;
        }
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


    public function processUpvotes($transactions, string $voter, int $userWeight, $votingType, int $limitMana)
    {
        try {
            foreach ($transactions as $tx) {
                $weight = $this->calculateVotingWeight($userWeight, $tx['weight'], $votingType);

                $tx['voter'] = $voter;
                $tx['weight'] = $weight;
                $tx['limitMana'] = $limitMana;
                // $tx['author']
                // $tx['permlink']

                $toVote = collect([
                    'voter' => $voter,
                    'author' => $tx['author'],
                    'permlink' => $tx['permlink'],
                    'weight' => $weight,
                    'limitMana' => $limitMana,
                    'method' => $votingType,
                ]);

                ProcessUpvoteJob::dispatch($toVote)->onQueue('voting');
            }
        } catch (\Throwable $th) {
            Log::warning("Process processUpvotes error: " . $th->getMessage());
        }
    }

    public function processBatchVotingJob(array $jobs)
    {
        Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // All jobs completed successfully...
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // First batch job failure detected...
                Log::error('error in batch ', [$e->getMessage(), $batch->id]);
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
