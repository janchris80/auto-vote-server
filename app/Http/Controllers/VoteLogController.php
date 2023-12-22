<?php

namespace App\Http\Controllers;

use App\Models\VoteLog;
use App\Http\Requests\StoreVoteLogRequest;
use App\Http\Requests\UpdateVoteLogRequest;

class VoteLogController extends Controller
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
    public function store(StoreVoteLogRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(VoteLog $voteLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVoteLogRequest $request, VoteLog $voteLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VoteLog $voteLog)
    {
        //
    }
}
