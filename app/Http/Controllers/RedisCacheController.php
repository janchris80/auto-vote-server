<?php

namespace App\Http\Controllers;

use App\Models\VoteLog;
use App\Http\Resources\VoteLogResource;
use App\Traits\HelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class RedisCacheController extends Controller
{
    use HelperTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $getDynamicGlobalProperties = $this->getDynamicGlobalProperties();

        return response()->json([
            'last_block' => cache('last_block'),
            'head_block_number' => $getDynamicGlobalProperties['head_block_number'] ?? null,
            'upvote_downvote_authors' => cache('upvote_downvote_authors'),
            'upvote_curator_authors' => cache('upvote_curator_authors'),
            'upvote_comment' => cache('upvote_comment'),
            'upvote_comment_authors' => cache('upvote_comment_authors'),
            'upvote_post_authors' => cache('upvote_post_authors'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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
    public function update(Request $request, VoteLog $voteLog)
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
