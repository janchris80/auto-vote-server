<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Http\Requests\StoreBlacklistRequest;
use App\Http\Requests\UpdateBlacklistRequest;

class BlacklistController extends Controller
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
     * @param  \App\Http\Requests\StoreBlacklistRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreBlacklistRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function show(Blacklist $blacklist)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateBlacklistRequest  $request
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateBlacklistRequest $request, Blacklist $blacklist)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Blacklist  $blacklist
     * @return \Illuminate\Http\Response
     */
    public function destroy(Blacklist $blacklist)
    {
        //
    }
}
