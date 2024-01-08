<?php

namespace App\Console\Commands;

use App\Models\Downvote;
use App\Models\Follower;
use App\Models\UpvoteComment;
use App\Models\UpvoteCurator;
use App\Models\UpvotePost;
use App\Traits\HelperTrait;
use Illuminate\Console\Command;

class SeederFromFollowerTableCommand extends Command
{
    use HelperTrait;

    protected $signature = 'seed:follower';

    protected $description = 'Just for testing';

    public function handle()
    {
        $followers = Follower::with(['user', 'follower'])->get();

        foreach ($followers as $follower) {
            if ($follower->trailer_type === 'curation') {
                UpvoteCurator::updateOrCreate([
                    'author' => $follower->user->username,
                    'voter' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at,
                ]);
            }

            if ($follower->trailer_type === 'upvote_comment') {
                UpvoteComment::updateOrCreate([
                    'commenter' => $follower->user->username,
                    'author' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at,
                ]);
            }

            if ($follower->trailer_type === 'upvote_post') {
                UpvotePost::updateOrCreate([
                    'author' => $follower->user->username,
                    'voter' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at,
                ]);
            }

            if ($follower->trailer_type === 'downvote') {
                Downvote::updateOrCreate([
                    'author' => $follower->user->username,
                    'voter' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at,
                ]);
            }
        }
    }
}
