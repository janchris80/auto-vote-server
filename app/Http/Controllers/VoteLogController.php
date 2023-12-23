<?php

namespace App\Http\Controllers;

use App\Models\VoteLog;
use App\Http\Resources\VoteLogResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoteLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $validate = $request->validate(['user' => 'required']);
            $voteLogs = VoteLog::where('voter', $validate['user'])
                ->paginate(100);

            return VoteLogResource::collection($voteLogs);
        } catch (ValidationException $e) {
            // Handle validation errors
            $errors = $e->errors();
            return response()->json(['error' => 'Validation failed', 'details' => $errors], 422);
        }
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
