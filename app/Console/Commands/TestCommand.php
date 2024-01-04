<?php

namespace App\Console\Commands;

use App\Models\Trailer;
use App\Models\UpvoteComment;
use App\Models\UpvoteCurator;
use App\Models\UpvotePost;
use App\Traits\HelperTrait;
use Hive\Hive;
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
        $followers = [
            [1, 2, 1, 'fixed', 'curation', 10000, 5, 5, 0, 0],
            [2, 2, 1, 'fixed', 'upvote_comment', 10000, 5, 5, 0, 0],
            [3, 2, 1, 'scaled', 'curation', 10000, 5, 5, 0, 0],
            [6, 2, 6, 'scaled', 'curation', 10000, 5, 5, 1, 0],
            [7, 2, 6, 'fixed', 'upvote_comment', 10000, 5, 5, 1, 0],
            [8, 6, 1, 'fixed', 'curation', 10000, 5, 5, 0, 0],
            [9, 6, 1, 'fixed', 'upvote_comment', 10000, 5, 5, 0, 0],
            [10, 2, 8, 'scaled', 'curation', 10000, 5, 5, 1, 0],
            [15, 10, 9, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [16, 18, 17, 'scaled', 'curation', 7000, 5, 5, 1, 0],
            [17, 6, 13, 'fixed', 'upvote_comment', 9000, 5, 5, 1, 0],
            [18, 13, 23, 'scaled', 'curation', 10000, 5, 5, 1, 0],
            [19, 13, 23, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [21, 28, 29, 'fixed', 'curation', 10000, 5, 5, 1, 0],
            [22, 25, 29, 'fixed', 'curation', 10000, 5, 5, 1, 0],
            [23, 30, 25, 'fixed', 'curation', 10000, 5, 5, 1, 0],
            [24, 31, 25, 'fixed', 'curation', 10000, 5, 5, 1, 0],
            [25, 32, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [26, 33, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [27, 34, 24, 'fixed', 'upvote_post', 10000, 5, 5, 0, 0],
            [28, 13, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [29, 32, 24, 'fixed', 'curation', 5000, 5, 5, 1, 0],
            [30, 35, 24, 'fixed', 'upvote_post', 1000, 5, 5, 0, 0],
            [31, 36, 24, 'fixed', 'upvote_post', 10000, 5, 5, 0, 0],
            [32, 22, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [33, 37, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [36, 6, 1, 'fixed', 'upvote_post', 10000, 5, 5, 0, 0],
            [37, 10, 40, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [39, 46, 6, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [40, 48, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [43, 2, 51, 'scaled', 'curation', 10000, 5, 5, 1, 0],
            [44, 53, 52, 'scaled', 'curation', 2000, 5, 5, 1, 0],
            [45, 54, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
            [46, 56, 24, 'fixed', 'upvote_post', 10000, 5, 5, 1, 0],
        ];

        $users = [
            'iamjco',
            'dbuzz',
            'loving-kindness',
            'aashirrshaikh',
            'iamjc93',
            'chrisrice',
            'dpservice',
            'cristinealimasac',
            'eddiespino',
            'aliento',
            'xpeng',
            'viggen',
            'guruvaj',
            'wset',
            'gianmarcobarel',
            'iviaxpow3r',
            'manuphotos',
            'hivecuba',
            'anadolu',
            'olexua',
            'vimukthi',
            'aifos',
            'johneth01',
            'whoswho',
            'efastromberg94',
            'stickupcurator',
            'stickupboys',
            'nyxlabs',
            'zeroooc',
            'lazy-panda',
            'hive-naija',
            'wrestorgonline',
            'suteru',
            'warofcriptonft',
            'thorlock',
            'splinterlands',
            'powerpaul',
            'dbuzz-ph',
            'mahdiyari',
            'eddiespinod',
            'curamax',
            'funtraveller',
            'darrenleesl',
            'customcar',
            'hafiz34',
            'emafe',
            'juneboy',
            'kungfukid',
            'gwajnberg',
            'hivepakistan',
            'kenny.rogers',
            'atma.love',
            'informationwar',
            'brobang',
            'sharmaine',
            'beeswap',
        ];

        $data = [];

        foreach ($followers as $follower) {
            $author = $users[$follower[1] - 1];
            $voter = $users[$follower[2] - 1];
            $voting_type = $follower[3];
            $trailer_type = $follower[4];
            $weight = $follower[5];
            $is_enable = $follower[8];

            $data[] = [
                'user_id' => $author,
                'follower_id' => $voter,
                'weight' => $weight,
                'voting_type' => $voting_type,
                'trailer_type' => $trailer_type,
                'is_enable' => $is_enable,
            ];
        }

        // foreach ($data as $follower) {
        //     if ($follower['trailer_type'] === 'curation') {
        //         UpvoteCurator::create([
        //             'author' => $follower['user_id'],
        //             'voter' => $follower['follower_id'],
        //             'is_enable' => $follower['is_enable'],
        //             'voter_weight' => $follower['weight'],
        //             'voting_type' => $follower['voting_type'],
        //         ]);
        //     }
        //     if ($follower['trailer_type'] === 'upvote_comment') {
        //         UpvoteComment::create([
        //             'author' => $follower['follower_id'],
        //             'commenter' => $follower['user_id'],
        //             'is_enable' => $follower['is_enable'],
        //             'voter_weight' => $follower['weight'],
        //             'voting_type' => $follower['voting_type'],
        //         ]);
        //     }
        //     if ($follower['trailer_type'] === 'upvote_post') {
        //         UpvotePost::create([
        //             'author' => $follower['user_id'],
        //             'voter' => $follower['follower_id'],
        //             'is_enable' => $follower['is_enable'],
        //             'voter_weight' => $follower['weight'],
        //             'voting_type' => $follower['voting_type'],
        //         ]);
        //     }
        // }

        // dump(array_unique($type));
    }
}
