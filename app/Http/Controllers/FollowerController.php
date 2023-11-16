<?php

namespace App\Http\Controllers;

use App\Http\Requests\FollowingFollowerRequest;
use App\Http\Requests\PopularFollowerRequest;
use App\Models\Follower;
use App\Http\Requests\StoreFollowerRequest;
use App\Http\Requests\UnfollowFollowerRequest;
use App\Http\Requests\UpdateFollowerRequest;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\PopularResource;
use App\Models\User;
use App\Traits\FollowerFunctionsTrait;
use App\Traits\HttpResponses;

class FollowerController extends Controller
{
    use HttpResponses, FollowerFunctionsTrait;

    public function popular(PopularFollowerRequest $request)
    {
        return $this->getPopular($request);
    }


    public function following(FollowingFollowerRequest $request)
    {
        return $this->getFollowing($request);
    }



    public function index()
    {
        //
    }

    public function store(StoreFollowerRequest $request)
    {
        $request->validated();
        $model = Follower::create([
            "user_id" => $request->user_id,
            "follower_id" => auth()->id(),
            "type" => $request->type,
        ]);

        return $this->success($model, 'Successfully Followed.');
    }

    public function follow(StoreFollowerRequest $request)
    {
        $request->validated();
        $model = Follower::create([
            "user_id" => $request->user_id,
            "follower_id" => auth()->id(),
            "type" => $request->type,
        ]);

        return $this->success($model, 'Successfully Followed.');
    }

    public function unfollow(UnfollowFollowerRequest $request)
    {
        $request->validated();
        $model = Follower::where("user_id", "=", $request->user_id)
            ->where("follower_id", auth()->id())
            ->where("type", "=", $request->type)
            ->delete();

        return $this->success($model, 'Successfully Unfollow.');
    }


    public function show(Follower $follower)
    {
        //
    }

    public function update(UpdateFollowerRequest $request, Follower $follower)
    {
        //
    }

    public function destroy(Follower $follower)
    {
        $follower->delete();

        return $this->success([], 'Successfully Unfollow.');
    }
}
