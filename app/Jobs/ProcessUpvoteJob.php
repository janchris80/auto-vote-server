<?php

namespace App\Jobs;

use App\Models\Vote;
use Hive\Helpers\PrivateKey;
use Hive\Hive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessUpvoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $votes;
    public $tries = 3;
    public $timeout = 300; // in seconds

    public function __construct($votes)
    {
        $this->votes = $votes;
    }

    public function handle(): void
    {
        $hive = new Hive();
        $postingKey = config('hive.private_key.posting'); // Be cautious with private keys
        $postingPrivateKey = new PrivateKey($postingKey);

        // Log::debug('Posting', [$postingPrivateKey]);

        $vote = (object)$this->votes->all();

        try {
            $activeVotes = $this->getActiveVotes($vote->voter, $vote->permlink);
            $isVoted = $activeVotes->contains('voter', $vote->voter);

            $canVote = !$isVoted
                && !$this->checkAccount($vote->voter, $vote->limitMana, $vote->method)
                && $this->checkResourceCredit($vote->voter);

            if ($canVote) {
                $this->broadcastVote($vote, $postingPrivateKey, $hive);
            }
            unset($vote);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function getActiveVotes($author, $permlink)
    {
        $response = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_active_votes',
            'params' => [$author, $permlink],
            'id' => 1,
        ]);

        return collect($response);
    }

    protected function makeHttpRequest($data)
    {
        // Replace with your actual HTTP request logic
        return Http::post('https://rpc.d.buzz/', $data)->json()['result'] ?? [];
    }

    public function checkResourceCredit($username)
    {
        $account = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'rc_api.find_rc_accounts',
            'params' => ['accounts' => [$username]], //
            'id' => 1,
        ]);

        $accountData = $account['rc_accounts'][0];
        $currentMana = (float) $accountData['rc_manabar']['current_mana'];
        $maxMana = (float) $accountData['max_rc'];

        // Calculate the percentage
        $percentage = ($currentMana / $maxMana) * 100;
        $percent = number_format($percentage, 2);

        return $percent > 2;
    }

    public function checkAccount($username, $limitMana, $method)
    {
        $account = $this->makeHttpRequest([
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_accounts',
            'params' => [[$username]], //
            'id' => 1,
        ]);
        $isLimitted = true;
        // Process the response
        if (!empty($account)) {
            $currentMana = $this->processAccountCurrentMana($account[0], $method);
            $isLimitted = intval($currentMana) <= intval($limitMana);
        }

        return $isLimitted;
    }

    protected function processAccountCurrentMana($account, $method)
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

        if ($method === 'downvote') {
            return $this->getDownvoteMana($account, $maxMana);
        } else {
            return $this->getUpvoteMana($account, $maxMana);
        }
    }

    public function getUpvoteMana($account, $maxMana)
    {
        $delta = time() - $account['voting_manabar']['last_update_time'];
        $current_mana = $account['voting_manabar']['current_mana'] + ($delta * $maxMana / 432000);
        $percentage = round($current_mana / $maxMana * 10000);

        if (!is_finite($percentage)) $percentage = 0;
        if ($percentage > 10000) $percentage = 10000;
        elseif ($percentage < 0) $percentage = 0;

        // $percent = number_format($percentage / 100, 2);

        return intval($percentage);
    }

    public function getDownvoteMana($account, $maxMana)
    {
        $currentPower = $account['downvote_manabar']['current_mana'];
        $lastUpdateTime = $account['downvote_manabar']['last_update_time'];
        $fullRechargeTime = 432000;

        $now = time();
        $secondsSinceUpdate = $now - $lastUpdateTime;

        $currentDownvotePower = ($currentPower + $secondsSinceUpdate * ($maxMana / $fullRechargeTime)) / $maxMana;
        $currentDownvotePower = min($currentDownvotePower, 1); // Cap at 100%

        // Convert to percentage and format to 2 decimal places
        $downvotePowerPercent = number_format($currentDownvotePower * 100, 2);

        return intval($downvotePowerPercent);
    }

    protected function broadcastVote($vote, $postingPrivateKey, $hive)
    {
        $weight = $vote->method === 'downvote' ? intval(-$vote->weight) : intval($vote->weight);
        $result = $hive->broadcast($postingPrivateKey, 'vote', [
            $vote->voter,      // voter
            $vote->author,     // author
            $vote->permlink,   // permlink
            $weight,           // weight
        ]);

        if (isset($result['trx_id'])) {
            Vote::updateOrCreate(
                [
                    'voter' => $vote->voter,
                    'author' => $vote->author,
                    'permlink' => $vote->permlink,
                ],
                [
                    'weight' => $vote->weight,
                    'is_voted' => true,
                ]
            );
            // Log::info('Voting result: ', $result);
        } else {
            // Log::error('Voting result: ', $result);
        }
    }
}
