<?php
// FollowerFunctionsTrait.php

namespace App\Traits;

use App\Http\Requests\FollowingFollowerRequest;
use App\Http\Requests\PopularFollowerRequest;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\PopularResource;
use App\Models\Follower;
use App\Models\User;

trait FollowerFunctionsTrait
{
    public function getPopular(PopularFollowerRequest $request)
    {
        $request->validated();

        $populars = User::query()
            ->with([
                'followings' => function ($query) use ($request) {
                    $query->where('type', '=', $request->type);
                }
            ])
            ->where('id', '!=', auth()->user()->id)
            ->withCount(['followings as followings_count' => function ($query) use ($request) {
                $query->where('type', $request->type);
            }])
            ->orderByDesc('followings_count')
            ->paginate(10);

        return PopularResource::collection($populars);
    }

    public function getFollowing(FollowingFollowerRequest $request)
    {
        $request->validated();

        $followings = User::query()
            ->whereHas('followings', function ($query) use ($request) {
                $query->where('type', $request->type)
                    ->where('follower_id', auth()->user()->id);
            })
            ->with([
                'followings' => function ($query) use ($request) {
                    $query->where('type', $request->type)
                        ->where('follower_id', auth()->user()->id);
                }
            ])
            ->withCount(['followings as followers_count' => function ($query) use ($request) {
                $query->where('type', $request->type)
                    ->where('follower_id', auth()->user()->id);
            }])
            ->orderByDesc('followers_count')
            ->paginate(10);

        return FollowingResource::collection($followings);
    }

    // Add other shared functions here
}
