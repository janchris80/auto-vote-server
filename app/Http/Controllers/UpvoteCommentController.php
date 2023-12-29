<?php

namespace App\Http\Controllers;

use App\Models\UpvoteComment;
use App\Http\Requests\StoreUpvoteCommentRequest;
use App\Http\Requests\UpdateUpvoteCommentRequest;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Cache;

class UpvoteCommentController extends Controller
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpvoteCommentRequest $request)
    {
        $request->validated();
        $user = auth()->user();

        UpvoteComment::updateOrCreate(
            [
                'author' => $user->username,
                'commenter' => $request->author,
            ],
            [
                'voter_weight' => $request->voterWeight,
                'is_enable' => $request->isEnable,
            ]
        );

        return $this->success([], 'Followed Successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(UpvoteComment $upvoteComment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUpvoteCommentRequest $request, UpvoteComment $upvoteComment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UpvoteComment $upvoteComment)
    {
        //
    }
}
