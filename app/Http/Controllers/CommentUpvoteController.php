<?php

namespace App\Http\Controllers;

use App\Models\CommentUpvote;
use App\Http\Requests\StoreCommentUpvoteRequest;
use App\Http\Requests\UpdateCommentUpvoteRequest;

class CommentUpvoteController extends Controller
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
     * @param  \App\Http\Requests\StoreCommentUpvoteRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCommentUpvoteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CommentUpvote  $commentUpvote
     * @return \Illuminate\Http\Response
     */
    public function show(CommentUpvote $commentUpvote)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCommentUpvoteRequest  $request
     * @param  \App\Models\CommentUpvote  $commentUpvote
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCommentUpvoteRequest $request, CommentUpvote $commentUpvote)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CommentUpvote  $commentUpvote
     * @return \Illuminate\Http\Response
     */
    public function destroy(CommentUpvote $commentUpvote)
    {
        //
    }
}
