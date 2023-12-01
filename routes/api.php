<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurationController;
use App\Http\Controllers\FollowerController;
use App\Http\Controllers\HiveController;
use App\Http\Controllers\TrailerController;
use Illuminate\Support\Facades\Route;


// public route
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// protected route
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/enable', [AuthController::class, 'updateEnable']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('account/history', [HiveController::class, 'accountHistory']);
    Route::post('account/votes', [HiveController::class, 'votes']);
    Route::post('account/search', [HiveController::class, 'searchAccount']);


    Route::post('/popular', [FollowerController::class, 'popular']);
    Route::post('/following', [FollowerController::class, 'following']);
    Route::post('/followers/follow', [FollowerController::class, 'follow']);
    Route::post('/followers/unfollow', [FollowerController::class, 'unfollow']);

    Route::post('/trailers/get', [TrailerController::class, 'index']);
    Route::post('/trailers', [TrailerController::class, 'store']);
    Route::put('/trailers', [TrailerController::class, 'update']);

    Route::put('/follower/update', [FollowerController::class, 'updateFollower']);

    Route::apiResource('/curations', CurationController::class);
    Route::apiResource('/followers', FollowerController::class);
    Route::apiResource('/hives', HiveController::class);
});
