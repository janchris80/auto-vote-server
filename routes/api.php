<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurationController;
use App\Http\Controllers\FollowerController;
use Illuminate\Support\Facades\Route;


// public route
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// protected route
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('/curations', CurationController::class);

    Route::post('/popular', [FollowerController::class, 'popular']);
    Route::post('/following', [FollowerController::class, 'following']);
    Route::post('/followers/follow', [FollowerController::class, 'follow']);
    Route::post('/followers/unfollow', [FollowerController::class, 'unfollow']);

    Route::apiResource('/followers', FollowerController::class);
});