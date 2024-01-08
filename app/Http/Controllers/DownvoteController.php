<?php

namespace App\Http\Controllers;

use App\Models\Downvote;
use App\Http\Requests\StoreDownvoteRequest;
use App\Http\Requests\UpdateDownvoteRequest;

class DownvoteController extends Controller
{
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
    public function store(StoreDownvoteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Downvote $downvote)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDownvoteRequest $request, Downvote $downvote)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Downvote $downvote)
    {
        //
    }
}
