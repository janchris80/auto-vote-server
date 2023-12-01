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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        $user_id = $request->user_id;

        $model = Follower::create([
            "user_id" => $user_id,
            "follower_id" => auth()->id(),
            "type" => $request->type,
        ]);

        $user = User::find($user_id);
        $username = $user->username;

        $response = Http::post('https://rpc.d.buzz/', [
            'jsonrpc' => '2.0',
            'method' => 'condenser_api.get_account_history',
            'params' => [$username, -1, 100],
            'id' => 1,
        ]);

        $response = $response->json();

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

    public function destroy(Follower $follower)
    {
        $follower->delete();

        return $this->success([], 'Successfully Unfollow.');
    }


    public function updateFollower(UpdateFollowerRequest $request)
    {
        $request->validated();

        $follower = Follower::find($request->id);

        $follower->update([
            'enable' => $request->status,
            'voting_type' => $request->method, // method
            'follower_type' => $request->type,
            'weight' => $request->weight,
            'after_min' => $request->waitTime, // wait time
            'daily_left' => $request->dailyLeft,
            'limit_left' => $request->limitLeft,
        ]);

        return $this->success($follower, 'User was successfully updated.');
    }
}
