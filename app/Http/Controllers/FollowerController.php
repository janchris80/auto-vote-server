<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Http\Requests\StoreFollowerRequest;
use App\Http\Requests\UnfollowFollowerRequest;
use App\Http\Requests\UpdateFollowerRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\CurationResource;
use App\Http\Resources\DownvoteResource;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\GetUserFollowerResource;
use App\Http\Resources\PopularResource;
use App\Http\Resources\PostResource;
use App\Models\Downvote;
use App\Models\DownvoteExcludedCommunity;
use App\Models\Trailer;
use App\Models\UpvoteComment;
use App\Models\UpvoteCommentExcludedCommunity;
use App\Models\UpvoteCurator;
use App\Models\UpvoteCuratorExcludedCommunity;
use App\Models\UpvotePost;
use App\Models\UpvotePostExcludedCommunity;
use App\Models\User;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowerController extends Controller
{
    use HttpResponses;


    public function getFollowing(Request $request)
    {
        return $this->getFollowingsByType($request, $request->type);
    }

    public function getPopular(Request $request)
    {
        return $this->getPopularByType($request, $request->type);
    }

    public function getPopularCuration()
    {
        $userId = auth()->id(); // Get the current user's ID
        $populars = User::query()
            ->whereHasTrailer('curation')
            ->withCount('followers')
            ->addSelect([
                'isFollowed' => Follower::select('follower_id')
                    ->whereColumn('user_id', 'users.id')
                    ->where('follower_id', $userId)
                    ->where('trailer_type', 'curation')
                    ->limit(1)
            ])
            ->paginate(10);

        return PopularResource::collection($populars);
    }

    public function getPopularDownvote()
    {
        $userId = auth()->id(); // Get the current user's ID

        $populars = User::query()
            ->whereHasTrailer('downvote')
            ->with([['followers', 'trailers']])
            ->withCount('followers')
            ->addSelect([
                'isFollowed' => Follower::select('follower_id')
                    ->whereColumn('user_id', 'users.id')
                    ->where('follower_id', $userId)
                    ->where('trailer_type', 'downvote')
                    ->limit(1)
            ])
            ->paginate(10);

        return PopularResource::collection($populars);
    }

    public function getFollowingCuration()
    {
        $user = auth()->user();
        $followings = UpvoteCurator::with(['followedUser', 'excludedCommunities'])
            ->from('upvote_curators as t')
            ->join(DB::raw('(SELECT author, COUNT(*) as followers_count FROM upvote_curators GROUP BY author) c'), 't.author', '=', 'c.author')
            ->where('t.voter', '=', $user->username)
            ->select('t.id', 't.author', 't.voter', 't.voter_weight', 't.is_enable', 't.voting_type', 't.voting_time', 'c.followers_count')
            ->paginate(10);

        // return ($followings);

        return CurationResource::collection($followings);
    }

    public function getFollowingDownvote()
    {
        // Get the ID of the authenticated user
        $user = auth()->user();
        $followings = Downvote::with(['followedUser', 'excludedCommunities'])
            ->from('downvotes as t')
            ->join(DB::raw('(SELECT author, COUNT(*) as followers_count FROM downvotes GROUP BY author) c'), 't.author', '=', 'c.author')
            ->where('t.voter', '=', $user->username)
            ->select('t.id', 't.author', 't.voter', 't.voter_weight', 't.is_enable', 't.voting_type', 't.voting_time', 'c.followers_count')
            ->paginate(10);

        return DownvoteResource::collection($followings);
    }

    public function getFollowingUpvoteComment()
    {
        // Get the ID of the authenticated user
        $user = auth()->user();

        $followings = UpvoteComment::with(['followedUser', 'excludedCommunities'])
            ->from('upvote_comments as t')
            ->join(DB::raw('(SELECT commenter, COUNT(*) as followers_count FROM upvote_comments GROUP BY commenter) c'), 't.commenter', '=', 'c.commenter')
            ->where('t.author', '=', $user->username)
            ->select('t.id', 't.author', 't.commenter', 't.voter_weight', 't.is_enable', 't.voting_type', 't.voting_time', 'c.followers_count')
            ->paginate(10);

        return CommentResource::collection($followings);
    }

    public function getFollowingUpvotePost()
    {
        $user = auth()->user();
        $followings = UpvotePost::with(['followedUser', 'excludedCommunities'])
            ->from('upvote_posts as t')
            ->join(DB::raw('(SELECT author, COUNT(*) as followers_count FROM upvote_posts GROUP BY author) c'), 't.author', '=', 'c.author')
            ->where('t.voter', '=', $user->username)
            ->select('t.id', 't.author', 't.voter', 't.voter_weight', 't.is_enable', 't.voting_type', 't.voting_time', 'c.followers_count')
            ->paginate(10);

        return PostResource::collection($followings);
    }

    public function follow(StoreFollowerRequest $request)
    {
        $request->validated();

        $trailer = Trailer::updateOrCreate(
            [
                'user_id' => $request->userId,
                'trailer_type' => $request->trailerType,
            ],
            [
                'description' => null,
            ]
        );

        $votingType = in_array($request->trailerType, ['upvote_comment', 'upvote_post'])
            ? 'fixed'
            : 'scaled';

        if ($trailer) {
            $follower = Follower::create([
                "user_id" => $request->userId,
                "follower_id" => auth()->id(),
                "trailer_type" => $request->trailerType,
                "voting_type" => $votingType,
                "is_enable" => true,
                "weight" => $request->weight ?? 5000, // to get the percent need to 5000 / 100 = 50%
            ]);

            if ($request->trailerType === 'upvote_comment') {
                UpvoteComment::updateOrCreate([
                    'commenter' => $follower->user->username,
                    'author' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at ?? now(),
                ]);
            }

            if ($request->trailerType === 'upvote_post') {
                UpvotePost::updateOrCreate([
                    'author' => $follower->user->username,
                    'voter' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at ?? now(),
                ]);
            }

            if ($request->trailerType === 'curation') {
                UpvoteCurator::updateOrCreate([
                    'author' => $follower->user->username,
                    'voter' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at ?? now(),
                ]);
            }

            if ($request->trailerType === 'downvote') {
                Downvote::updateOrCreate([
                    'author' => $follower->user->username,
                    'voter' => $follower->follower->username,
                ], [
                    'voter_weight' => $follower->weight,
                    'is_enable' => $follower->is_enable,
                    'voting_type' => $follower->voting_type,
                    'last_voted_at' => $follower->last_voted_at ?? now(),
                ]);
            }
        }

        return $this->success($follower, 'Successfully Followed.');
    }

    public function unfollow(UnfollowFollowerRequest $request)
    {
        $request->validated();
        $follower = Follower::where("user_id", "=", $request->userId)
            ->where("follower_id", auth()->id())
            ->where("trailer_type", "=", $request->trailerType)
            ->delete();

        $followedUser = User::find($request->userId);
        $user = auth()->user();

        if ($request->trailerType === 'upvote_comment') {
            UpvoteComment::query()
                ->where('author', $user->username)
                ->where('commenter', $followedUser->username)
                ->delete();
        }

        if ($request->trailerType === 'upvote_post') {
            UpvotePost::query()
                ->where('voter', $user->username)
                ->where('author', $followedUser->username)
                ->delete();
        }

        if ($request->trailerType === 'curation') {
            UpvoteCurator::query()
                ->where('voter', $user->username)
                ->where('author', $followedUser->username)
                ->delete();
        }

        if ($request->trailerType === 'downvote') {
            Downvote::query()
                ->where('voter', $user->username)
                ->where('author', $followedUser->username)
                ->delete();
        }

        return $this->success($follower, 'Successfully Unfollow.');
    }

    public function update(UpdateFollowerRequest $request)
    {
        $request->validated();
        $trailerType = strtolower($request->trailerType);
        $communities = $request->communities;
        $user = auth()->user();

        if ($trailerType === 'upvote_post') {
            $follower = UpvotePost::find($request->id);
            $excluded = UpvotePostExcludedCommunity::query();
        }

        if ($trailerType === 'upvote_comment') {
            $follower = UpvoteComment::find($request->id);
            $excluded = UpvoteCommentExcludedCommunity::query();
        }

        if ($trailerType === 'curation') {
            $follower = UpvoteCurator::find($request->id);
            $excluded = UpvoteCuratorExcludedCommunity::query();
        }

        if ($trailerType === 'downvote') {
            $follower = Downvote::find($request->id);
            $excluded = DownvoteExcludedCommunity::query();
        }

        if (!$follower) {
            return $this->error(null, 'Follower not found', 404);
        }

        $follower->is_enable = $request->isEnable;
        $follower->voting_type = $request->votingType ? strtolower($request->votingType) : 'fixed';
        $follower->voter_weight = $request->weight * 100;
        $follower->voting_time = $request->votingTime;
        $follower->save();

        $excluded->where('upvote_id', $follower->id)
            ->updateOrCreate(
                [
                    'upvote_id' => $follower->id,
                ],
                [
                    'list' => json_encode($communities),
                ]
            );

        return $this->success($follower, 'User was successfully updated.');
    }

    public function getUserFollower(Request $request)
    {
        $username = $request->username;
        $trailerType = $request->trailerType;
        $followers = collect();

        if ($trailerType === 'curation') {
            $followers = UpvoteCurator::query()
                ->where('author', $username)
                ->get();
        }

        if ($trailerType === 'downvote') {
            $followers = Downvote::query()
                ->where('author', $username)
                ->get();
        }

        if ($trailerType === 'upvote_post') {
            $followers = UpvotePost::query()
                ->where('author', $username)
                ->get();
        }

        if ($trailerType === 'upvote_comment') {
            $followers = UpvoteComment::query()
                ->select([
                    'author as voter',
                    'commenter as author',
                    'voter_weight',
                    'is_enable',
                    'voting_time',
                    'voting_type',
                ])
                ->where('commenter', $username)
                ->get();
        }

        return GetUserFollowerResource::collection($followers);
    }

    private function getFollowingsByType($request, $type)
    {
        $method = 'getFollowing' . $this->snakeToCamel($type);
        return method_exists($this, $method)
            ? $this->$method($request)
            : FollowingResource::collection([]);
    }

    private function getPopularByType($request, $type)
    {
        $method = 'getPopular' . $this->snakeToCamel($type);
        return method_exists($this, $method)
            ? $this->$method($request)
            : PopularResource::collection([]);
    }

    private function snakeToCamel($string)
    {
        $words = explode('_', $string); // Split the string into an array on underscores
        $words = array_map('ucfirst', $words); // Capitalize the first letter of each word
        return implode('', $words); // Concatenate them back together
    }
}
