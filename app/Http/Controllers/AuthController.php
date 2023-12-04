<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\HttpResponses;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use HttpResponses;

    public function login(LoginRequest $request)
    {
        $request->validated();

        $user = User::where('username', $request->username)
            ->with('trailers')
            ->first();

        if (!$user) {
            $user = User::create([
                'username' => $request->username,
            ]);
        }

        Auth::login($user);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $user->createToken('API Token of ' . $user->username)->plainTextToken,
        ], "Login Successfully.");
    }

    public function register(StoreUserRequest $request)
    {
        $request->validated();

        $user = User::create([
            'username' => $request->username,
        ]);

        return $this->success($user, "Register Successfully");
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->success([], "You have successfully been logged out and token has been deleted.");
    }

    public function user()
    {
        $user = User::with(['curationTrailer', 'downvoteTrailer'])
            ->find(auth()->id());

        return $this->success(new UserResource($user));
    }

    public function updateEnable()
    {
        // Find the current authenticated user
        $user = Auth::user();

        // Update the 'enable' column
        $user->update(['is_enable' => !$user->is_enable]);
    }

    public function update(UpdateUserRequest $request)
    {
        $request->validated();
        // Find the current authenticated user
        $userId = auth()->id();
        $user = User::find($userId);

        if ($request->type === 'upvote') {
            $user->limit_upvote_mana = $request->limitPower * 100;
        }

        if ($request->type === 'downvote') {
            $user->limit_downvote_mana = $request->limitPower * 100;
        }

        if ($request->type === 'is_auto_claim_reward') {
            $user->is_auto_claim_reward = $request->isAutoClaimReward;
        }

        if ($request->type === 'is_enable') {
            $user->is_enable = $request->isEnable;
        }

        $user->is_pause = $request->isPause;
        $user->save();

        return $this->success(new UserResource($user));
    }
}
