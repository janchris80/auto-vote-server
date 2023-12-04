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
use App\Models\Trailer;
use App\Models\User;
use App\Traits\FollowerFunctionsTrait;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Http;

class FollowerController extends Controller
{
    use HttpResponses, FollowerFunctionsTrait;

    public function popular(PopularFollowerRequest $request)
    {
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


    public function following(FollowingFollowerRequest $request)
    {
        $request->validated();

        // Get the ID of the authenticated user
        $userId = auth()->id();
        $type = $request->type;
        $relation = $type . "Trailer"; // name of relation in user model

        $followings = Trailer::query()
            ->where('type', $type)
            ->whereHas('user.follower', function ($query) use ($userId, $type) {
                $query->where('follower_id', $userId)
                    ->where('follower_type', $type);
            })
            ->with([
                'user.follower' => function ($query) use ($userId, $type) {
                    $query->where('follower_id', $userId)
                        ->where('follower_type', $type);
                },
            ])
            ->paginate(10);

        return FollowingResource::collection($followings);
    }



    public function index()
    {
        //
    }

    public function store(StoreFollowerRequest $request)
    {
        //
    }

    public function follow(StoreFollowerRequest $request)
    {
        $request->validated();
        $follower = Follower::create([
            "user_id" => $request->userId,
            "follower_id" => auth()->id(),
            "follower_type" => $request->type,
            "voting_type" => 'scaled',
            "enable" => true,
            "weight" => 5000, // to get the percent need to 5000 / 100 = 50%
        ]);

        $trailer = Trailer::firstOrCreate(
            ['user_id' => $request->userId, 'type' => $request->type],
            ['description' => 'None']
        );


        return $this->success($follower, 'Successfully Followed.');
    }

    public function unfollow(UnfollowFollowerRequest $request)
    {
        $request->validated();
        $model = Follower::where("user_id", "=", $request->userId)
            ->where("follower_id", auth()->id())
            ->where("follower_type", "=", $request->type)
            ->delete();

        return $this->success($model, 'Successfully Unfollow.');
    }

    public function destroy(Follower $follower)
    {
        //
    }


    public function update(UpdateFollowerRequest $request)
    {
        $request->validated();

        $follower = Follower::find($request->id);

        $follower->enable = $request->isEnable;
        $follower->voting_type = strtolower($request->votingType);
        $follower->follower_type = strtolower($request->type);
        $follower->weight = $request->weight * 100;
        $follower->after_min = $request->afterMin;
        $follower->daily_limit = $request->dailyLimit;
        $follower->limit_left = $request->limitLeft;
        $follower->save();

        return $this->success($follower, 'User was successfully updated.');
    }
}
