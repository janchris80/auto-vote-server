<?php

namespace App\Http\Controllers;

use App\Models\UpvotePost;
use App\Http\Requests\StoreUpvotePostRequest;
use App\Http\Requests\UpdateUpvotePostRequest;
use App\Traits\HttpResponses;

class UpvotePostController extends Controller
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
    public function store(StoreUpvotePostRequest $request)
    {
        $request->validated();
        $user = auth()->user();

        UpvotePost::updateOrCreate(
            [
                'voter' => $user->username,
                'author' => $request->author,
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
    public function show(UpvotePost $upvotePost)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUpvotePostRequest $request, UpvotePost $upvotePost)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UpvotePost $upvotePost)
    {
        //
    }
}
