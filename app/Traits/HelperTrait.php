<?php

namespace App\Traits;

use App\Jobs\V1\ProcessUpvoteJob;
use App\Models\Downvote;
use App\Models\UpvoteComment;
use App\Models\UpvoteCurator;
use App\Models\UpvotePost;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Hive\Hive;
use Illuminate\Bus\Batch;
use Illuminate\Support\Arr;
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
    public $fiveMinutesInSecond = 300; // seconds
    public $downvoteManaRatio  = 0.25;
    public $maxIndex = 500;
    public $rpcNodes = [
        'https://rpc.d.buzz/',
        'https://api.hive.blog',
        'https://rpc.ecency.com/',
        'https://hive-api.3speak.tv/',
        'https://hived.privex.io',
        'https://anyx.io',
        'https://api.deathwing.me',
        'https://hived.emre.sh',
        'https://hive-api.arcange.eu',
        'https://api.openhive.network',
        'https://techcoderx.com',
        'https://hive.roelandp.nl',
        'https://api.c0ff33a.uk',
    ];
    public $timeout = 300;

    public function hive()
    {
        $hive = new Hive([
            'rpcNodes' => $this->rpcNodes,
            'timeout' => $this->timeout,
        ]);

        return $hive;
    }

    public function privateKey()
    {
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = $this->hive()->privateKeyFrom($postingKey);

        return $postingPrivateKey;
    }

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
    public function getApiData(string $method, array $params, int $loop = 0): array
    {
        // try {


        //     // Decode and return the JSON response
        //     return $response->json()['result'] ?? [];
        // } catch (\Exception $e) {
        //     Log::error('Error in getApiData ' . $method . ': ' . $e->getMessage());
        //     return [];
        // }

        $response = Http::timeout($this->timeout)
            ->post($this->rpcNodes[$loop], [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ]);

        if (!$response->successful()) {
            if (sizeof($this->rpcNodes) - 1 > $loop) {
                return $this->getApiData($method, $params, ++$loop);
            } else {
                throw new \Exception('Network failed after ' . $loop . ' attempts');
            }
        } else {
            $json = $response->json();

            if (array_key_exists('result', $json)) {
                return $json['result'];
            }

            if (array_key_exists('error', $json)) {
                $error = $json['error'];
                if (array_key_exists('code', $error)) {
                    // -32003
                    // Unable to acquire database lock
                    if ($error['code'] === -32003 && $error['message'] === 'Unable to acquire database lock') {
                        if (sizeof($this->rpcNodes) - 1 > $loop) {
                            return $this->getApiData($method, $params, ++$loop);
                        } else {
                            throw new \Exception('Network failed after ' . $loop . ' attempts');
                        }
                    }
                    return [];
                }
            }

            // return $json['error'];
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
        $usernames = Arr::wrap($username);

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

    public function getContent($author, $permlink): Collection
    {
        $response = $this->getApiData('condenser_api.get_content', [$author, $permlink]);

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
            ->onConnection('redis')
            ->dispatch();
    }

    public function getDynamicGlobalProperties()
    {
        return Cache::remember('get_dynamic_global_properties', $this->fiveMinutesInSecond, function () {
            return $this->getApiData('condenser_api.get_dynamic_global_properties', []);
        });
    }

    public function getLastBlock()
    {
        return Cache::rememberForever('last_block', function () {
            return 0; // Default value if not found in cache
        });
    }

    public function checkLimits($voter, $author, $permlink, $weight)
    {
        try {
            // Fetch user's power limit from the database
            $powerlimit = User::select('limit_upvote_mana')
                ->where('is_enable', 1)
                ->where('is_pause', 0)
                ->where('username', $voter)
                ->value('limit_upvote_mana');


            if (!$powerlimit) {
                return false;
            }

            // Fetch user details from the blockchain (adjust the following code based on your actual implementation)
            $account = $this->getAccounts($voter)->first();

            // On any error, account will be null
            if (!$account) {
                return false;
            }

            $getDynamicglobalProperties = $this->getDynamicGlobalProperties();
            $tvfs = (int)str_replace('HIVE', '', $getDynamicglobalProperties['total_vesting_fund_hive']);
            $tvs = (int)str_replace('VESTS', '', $getDynamicglobalProperties['total_vesting_shares']);
            // dump($getDynamicglobalProperties, $tvfs, $tvs);

            // Extract necessary information from the user details
            if ($tvfs && $tvs) {

                // Calculating total SP to check against limitation
                $delegated = (int) str_replace('VESTS', '', $account['delegated_vesting_shares']);
                $received = (int) str_replace('VESTS', '', $account['received_vesting_shares']);
                $vesting = (int) str_replace('VESTS', '', $account['vesting_shares']);
                $totalvest = $vesting + $received - $delegated;
                $sp = $totalvest * ($tvfs / $tvs);
                $sp = round($sp, 2);

                // Calculating Mana to check against limitation
                $withdrawRate = 0;

                if ((int)str_replace('VESTS', '', $account['vesting_withdraw_rate']) > 0) {
                    $withdrawRate = min(
                        (int)str_replace('VESTS', '', $account['vesting_withdraw_rate']),
                        (int)(($account['to_withdraw'] - $account['withdrawn']) / 1000000)
                    );
                }

                $maxMana = ($totalvest - $withdrawRate) * pow(10, 6);

                if ($maxMana === 0) {
                    return false;
                }

                $delta = Carbon::now()->timestamp - $account['voting_manabar']['last_update_time'];
                $currentMana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);
                $percentage = round($currentMana / $maxMana * 10000);

                if (!is_finite($percentage)) {
                    $percentage = 0;
                }

                if ($percentage > 10000) {
                    $percentage = 10000;
                } elseif ($percentage < 0) {
                    $percentage = 0;
                }

                $powernow = round($percentage, 2);

                if ($powernow > $powerlimit) {
                    if (($powernow / 100) * ($weight / 10000) * $sp > 3) {
                        // Don't broadcast upvote if sp*weight*power < 3
                        return true;
                    }

                    return false;
                }

                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function fetchUpvotePostAuthors(): array
    {
        return Cache::remember('upvote_post_authors', $this->fiveMinutesInSecond, function () {
            return UpvotePost::query()
                ->whereHas('user', function ($query) {
                    $query->where('is_enable', true);
                })
                ->where('is_enable', true)
                ->distinct()
                ->pluck('author')
                ->toArray();
        });
    }

    public function fetchUpvotePosts($author)
    {
        return UpvotePost::query()
            ->whereHas('user', function ($query) {
                $query->where('is_enable', true);
            })
            ->where('is_enable', true)
            ->where('author', $author)
            ->get();
    }

    protected function fetchUpvoteCommentAuthors(): array
    {
        return Cache::remember('upvote_comment_authors', $this->fiveMinutesInSecond, function () {
            return UpvoteComment::query()
                ->whereHas('user', function ($query) {
                    $query->where('is_enable', true);
                })
                ->where('is_enable', true)
                ->distinct()
                ->pluck('author')
                ->toArray();
        });
    }

    protected function fetchUpvoteComments()
    {
        return Cache::remember('upvote_comment', $this->fiveMinutesInSecond, function () {
            return UpvoteComment::query()
                ->select(
                    'author',
                    'commenter',
                    'voter_weight',
                    'is_enable',
                    'voting_type',
                    'last_voted_at'
                )
                ->where('is_enable', true)
                ->whereHas('user', function ($query) {
                    $query->where('is_enable', true);
                })
                ->get();
        });
    }

    protected function fetchUpvoteCurationFollowedAuthors(): array
    {
        return Cache::remember('upvote_curator_authors', $this->fiveMinutesInSecond, function () {
            return UpvoteCurator::query()
                ->whereHas('user', function ($query) {
                    $query->where('is_enable', true);
                })
                ->where('is_enable', true)
                ->distinct()
                ->pluck('author')
                ->toArray();
        });
    }

    protected function fetchDownvoteFollowedAuthors(): array
    {
        return Cache::remember('upvote_downvote_authors', $this->fiveMinutesInSecond, function () {
            return Downvote::select('author')
                ->whereHas('user', function ($query) {
                    $query->where('is_enable', true);
                })
                ->where('is_enable', true)
                ->distinct()
                ->pluck('author')
                ->toArray();
        });
    }
}
