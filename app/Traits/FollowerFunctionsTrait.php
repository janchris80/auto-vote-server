<?php
// FollowerFunctionsTrait.php

namespace App\Traits;

use App\Http\Requests\FollowingFollowerRequest;
use App\Http\Requests\PopularFollowerRequest;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\PopularResource;
use App\Models\Follower;
use App\Models\Trailer;
use App\Models\User;

trait FollowerFunctionsTrait
{
    public function getPopular(PopularFollowerRequest $request)
    {
        $request->validated();

        // $populars = User::query()
        //     ->with([
        //         'followings' => function ($query) use ($request) {
        //             $query->where('type', '=', $request->type);
        //         }
        //     ])
        //     ->where('id', '!=', auth()->user()->id)
        //     ->withCount(['followings as followings_count' => function ($query) use ($request) {
        //         $query->where('type', $request->type);
        //     }])
        //     ->orderByDesc('followings_count')
        //     ->paginate(10);

        // return PopularResource::collection($populars);

        $userId = auth()->id(); // Get the current user's ID

        $populars = Trailer::query()
            ->where('type', '=', $request->type)
            ->with(['user' => function ($query) use ($userId) {
                $query->withCount('followersCount')
                    ->addSelect([
                        'isFollowed' => Follower::select('id')
                            ->whereColumn('user_id', 'users.id')
                            ->where('follower_id', $userId)
                            ->limit(1)
                    ]);
            }])
            ->paginate(10);

        return PopularResource::collection($populars);
    }

    // public function getFollowing(FollowingFollowerRequest $request)
    // {
    // $request->validated();

    // $followings = User::query()
    //     ->whereHas('followings', function ($query) use ($request) {
    //         $query->where('type', $request->type)
    //             ->where('follower_id', auth()->user()->id);
    //     })
    //     ->with([
    //         'followings' => function ($query) use ($request) {
    //             $query->where('type', $request->type)
    //                 ->where('follower_id', auth()->user()->id);
    //         }
    //     ])
    //     ->withCount(['followings as followers_count' => function ($query) use ($request) {
    //         $query->where('type', $request->type)
    //             ->where('follower_id', auth()->user()->id);
    //     }])
    //     ->orderByDesc('followers_count')
    //     ->paginate(10);
    // }

    public function getFollowing(FollowingFollowerRequest $request)
    {
        $request->validated();

        // Get the ID of the authenticated user
        $userId = auth()->id();
        $type = $request->type;
        $relation = $type . "Trailer"; // name of relation in user model

        // Query to get the followings
        // $followings = Trailer::query()
        //     ->whereHas('user.followers', function ($query) use ($userId, $type) {
        //         // Ensure the follower is the authenticated user
        //         $query->where('follower_id', $userId);
        //     })
        //     ->with(['user', 'user.followers', 'user.followersCount', 'user.follower'])
        //     // ->paginate(10);
        //     ->get();
        //     return $followings;

        $followings = Trailer::query()
            ->where('type', $type)
            ->whereHas('user.follower', function ($query) use ($userId, $type) {
                $query->where('follower_id', $userId)
                    ->where('follower_type', $type);
            })
            ->with([
                'user.follower',
            ])
            ->paginate(10);

        // return $followings;

        return FollowingResource::collection($followings);
    }
}
