<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Http\Requests\StoreFollowerRequest;
use App\Http\Requests\UnfollowFollowerRequest;
use App\Http\Requests\UpdateFollowerRequest;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\FollowingUpvoteCommentResource;
use App\Http\Resources\FollowingUpvotePostResource;
use App\Http\Resources\PopularResource;
use App\Models\Trailer;
use App\Models\User;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

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
        // Get the ID of the authenticated user
        $userId = auth()->id();

        $followings = User::query()
            ->whereHasFollower($userId, 'curation')
            ->withFollower($userId, 'curation')
            ->paginate(10);

        return FollowingResource::collection($followings);
    }

    public function getFollowingDownvote()
    {
        // Get the ID of the authenticated user
        $userId = auth()->id();

        $followings = User::query()
            ->whereHasFollower($userId, 'downvote')
            ->withFollower($userId, 'downvote')
            ->paginate(10);

        return FollowingResource::collection($followings);
    }

    public function getFollowingUpvoteComment()
    {
        // Get the ID of the authenticated user
        $userId = auth()->id();

        $followings = User::query()
            ->whereHasFollower($userId, 'upvote_comment')
            ->withFollower($userId, 'upvote_comment')
            ->paginate(10);

        return FollowingUpvoteCommentResource::collection($followings);
    }

    public function getFollowingUpvotePost()
    {
        // Get the ID of the authenticated user
        $userId = auth()->id();

        $followings = User::query()
            ->whereHasFollower($userId, 'upvote_post')
            ->withFollower($userId, 'upvote_post')
            ->paginate(10);

        return FollowingUpvotePostResource::collection($followings);
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
                "voting_type" =>$votingType,
                "enable" => true,
                "weight" => $request->weight ?? 10000, // to get the percent need to 10000 / 100 = 100%
            ]);
        }

        return $this->success($follower, 'Successfully Followed.');
    }

    public function unfollow(UnfollowFollowerRequest $request)
    {
        $request->validated();
        $model = Follower::where("user_id", "=", $request->userId)
            ->where("follower_id", auth()->id())
            ->where("trailer_type", "=", $request->trailerType)
            ->delete();

        return $this->success($model, 'Successfully Unfollow.');
    }

    public function update(UpdateFollowerRequest $request)
    {
        $request->validated();

        $follower = Follower::find($request->id);

        if (!$follower) {
            return $this->error(null, 'Follower not found', 404);
        }

        $follower->is_enable = $request->isEnable;
        $follower->voting_type = $request->votingType ? strtolower($request->votingType) : 'fixed';
        $follower->trailer_type = strtolower($request->trailerType);
        $follower->weight = $request->weight * 100;
        $follower->save();

        return $this->success($follower, 'User was successfully updated.');
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
