<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurationController;
use App\Http\Controllers\FollowerController;
use App\Http\Controllers\HiveController;
use App\Http\Controllers\TrailerController;
use App\Http\Controllers\UpvotedCommentController;
use App\Models\UpvotedComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Check API status
Route::get('/status', function () {
    $dbStatus = false;

    try {
        // Attempt to connect to the database
        DB::connection()->getPdo();
        $dbStatus = true;
    } catch (\Exception $e) {
        // Connection could not be established
        // You can log the error or handle it as needed
        $dbStatus = false;
    }

    return [
        'php_version' => phpversion(),
        'app_name' => config('app.name'),
        'environment' => config('app.env'),
        'db_status' => $dbStatus,
        // Add more information as needed
    ];
});

// public route
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// protected route
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/enable', [AuthController::class, 'updateEnable']);
    Route::post('/user/update', [AuthController::class, 'update']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('account/history', [HiveController::class, 'accountHistory']);
    Route::post('account/votes', [HiveController::class, 'votes']);
    Route::post('account/search', [HiveController::class, 'searchAccount']);

    Route::get('/following/curation', [FollowerController::class, 'getFollowingCuration']);
    Route::get('/following/downvote', [FollowerController::class, 'getFollowingDownvote']);
    Route::get('/following/upvote-comment', [FollowerController::class, 'getFollowingUpvoteComment']);
    Route::get('/following/upvote-post', [FollowerController::class, 'getFollowingUpvotePost']);

    Route::get('/popular/curation', [FollowerController::class, 'getPopularCuration']);
    Route::get('/popular/downvote', [FollowerController::class, 'getPopularDownvote']);
    Route::get('/popular/upvote-comment', [FollowerController::class, 'getPopularUpvoteComment']);
    Route::get('/popular/upvote-post', [FollowerController::class, 'getPopularUpvotePost']);


    Route::post('/followers/follow', [FollowerController::class, 'follow']);
    Route::post('/followers/unfollow', [FollowerController::class, 'unfollow']);

    Route::post('/trailers/get', [TrailerController::class, 'index']);
    Route::post('/trailers', [TrailerController::class, 'store']);
    Route::put('/trailers', [TrailerController::class, 'update']);

    Route::put('/follower/update', [FollowerController::class, 'update']);

    Route::apiResource('/curations', CurationController::class);
    Route::apiResource('/followers', FollowerController::class);
    Route::apiResource('/hives', HiveController::class);

    Route::apiResource('upvote-comments', UpvotedCommentController::class);
    Route::apiResource('upvoted-comments', UpvotedCommentController::class);
});
