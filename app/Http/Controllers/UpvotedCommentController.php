<?php

namespace App\Http\Controllers;

use App\Models\UpvotedComment;
use App\Http\Requests\StoreUpvotedCommentRequest;
use App\Http\Requests\UpdateUpvotedCommentRequest;

class UpvotedCommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreUpvotedCommentRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpvotedCommentRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\UpvotedComment  $upvotedComment
     * @return \Illuminate\Http\Response
     */
    public function show(UpvotedComment $upvotedComment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateUpvotedCommentRequest  $request
     * @param  \App\Models\UpvotedComment  $upvotedComment
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUpvotedCommentRequest $request, UpvotedComment $upvotedComment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UpvotedComment  $upvotedComment
     * @return \Illuminate\Http\Response
     */
    public function destroy(UpvotedComment $upvotedComment)
    {
        //
    }
}
