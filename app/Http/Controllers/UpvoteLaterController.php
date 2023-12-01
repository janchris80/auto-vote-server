<?php

namespace App\Http\Controllers;

use App\Models\UpvoteLater;
use App\Http\Requests\StoreUpvoteLaterRequest;
use App\Http\Requests\UpdateUpvoteLaterRequest;

class UpvoteLaterController extends Controller
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
     * @param  \App\Http\Requests\StoreUpvoteLaterRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpvoteLaterRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\UpvoteLater  $upvoteLater
     * @return \Illuminate\Http\Response
     */
    public function show(UpvoteLater $upvoteLater)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateUpvoteLaterRequest  $request
     * @param  \App\Models\UpvoteLater  $upvoteLater
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUpvoteLaterRequest $request, UpvoteLater $upvoteLater)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UpvoteLater  $upvoteLater
     * @return \Illuminate\Http\Response
     */
    public function destroy(UpvoteLater $upvoteLater)
    {
        //
    }
}
