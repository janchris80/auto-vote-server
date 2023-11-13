<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\HttpResponses;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use HttpResponses;

    public function login(LoginRequest $request)
    {
        $request->validated($request->all());

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            $user = User::create([
                'username' => $request->username,
            ]);
        }

        Auth::login($user);

        return $this->success([
            'user' => [
                'id' => (string)$user->id, // Cast the ID to a string
                'username' => $user->username,
            ],
            'token' => $user->createToken('API Token of ' . $user->username)->plainTextToken,
        ], "Login Successfully.");
    }

    public function register(StoreUserRequest $request)
    {
        $request->validated($request->all());

        $user = User::create([
            'username' => $request->username,
        ]);

        return $this->success([
            'user' => $user,
            // 'token' => $user->createToken('API Token of ' . $user->username)->plainTextToken,
        ], "Register Successfully");
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->success([], "You have successfully been logged out and token has been deleted.");
    }

    public function test()
    {
        return 'test';
    }
}
