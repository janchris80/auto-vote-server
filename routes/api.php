<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FollowerController;
use App\Http\Controllers\HiveController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TrailerController;
use App\Http\Controllers\VoteLogController;
use Illuminate\Support\Facades\Route;


// public route

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});


Route::middleware('throttle:10,1')->group(function () {
    // Your API routes go here
    Route::get('/vote/logs', [VoteLogController::class, 'index']);
    // Check API status
    Route::get('/status', StatusController::class);
});

// protected route
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/enable', [AuthController::class, 'updateEnable']);
    Route::post('/user/update', [AuthController::class, 'update']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('account/history', [HiveController::class, 'accountHistory']);
    Route::post('account/votes', [HiveController::class, 'votes']);
    Route::post('account/search', [HiveController::class, 'searchAccount']);

    Route::get('/following', [FollowerController::class, 'getFollowing']);
    Route::get('/popular', [FollowerController::class, 'getPopular']);

    Route::post('/user/followers', [FollowerController::class, 'getUserFollower']);

    Route::post('/followers/follow', [FollowerController::class, 'follow']);
    Route::post('/followers/unfollow', [FollowerController::class, 'unfollow']);

    Route::post('/trailers/get', [TrailerController::class, 'index']);
    Route::post('/trailers', [TrailerController::class, 'store']);
    Route::put('/trailers', [TrailerController::class, 'update']);

    Route::put('/follower/update', [FollowerController::class, 'update']);
});
