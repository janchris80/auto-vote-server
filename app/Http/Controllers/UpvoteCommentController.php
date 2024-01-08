<?php

namespace App\Http\Controllers;

use App\Models\UpvoteComment;
use App\Http\Requests\StoreUpvoteCommentRequest;
use App\Http\Requests\UpdateUpvoteCommentRequest;
use App\Http\Resources\CommentResource;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpvoteCommentController extends Controller
{
    use HttpResponses;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $data = UpvoteComment::with('user')
            ->from('upvote_comments as t')
            ->join(DB::raw('(SELECT commenter, COUNT(*) as commenter_count FROM upvote_comments GROUP BY commenter) c'), 't.commenter', '=', 'c.commenter')
            ->where('t.author', '=', $user->username)
            ->select('t.id', 't.author', 't.commenter', 't.voter_weight', 't.is_enable', 't.voting_type', 'c.commenter_count')
            ->paginate(10);
        return $data;

        return CommentResource::collection($data);
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
