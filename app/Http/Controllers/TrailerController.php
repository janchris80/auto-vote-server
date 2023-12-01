<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetTrailerRequest;
use App\Models\Trailer;
use App\Http\Requests\StoreTrailerRequest;
use App\Http\Requests\UpdateTrailerRequest;
use App\Http\Resources\TrailerResource;
use App\Traits\HttpResponses;
use GuzzleHttp\Psr7\Request;

class TrailerController extends Controller
{
    use HttpResponses;

    public function index(GetTrailerRequest $request)
    {
        $request->validated();

        $trailer = Trailer::where('user_id', '=', auth()->id())
            ->where('type', '=', $request->type)
            ->first();

        if (!$trailer) {
            return $this->error([], 'No data found.', 204);
        }

        return $this->success(new TrailerResource($trailer));
    }

    public function store(StoreTrailerRequest $request)
    {
        $request->validated();

        $trailer = Trailer::create([
            'user_id' => auth()->id(),
            'description' => $request->description,
            'type' => $request->type,
        ]);

        return $this->success($trailer, 'Trailer create successfully.', 201);
    }

    public function update(UpdateTrailerRequest $request)
    {
        $request->validated();

        $trailer = Trailer::find($request->id);

        $trailer->update([
            'description' => $request->description,
            'type' => $request->type,
        ]);

        return $this->success($trailer, 'Trailer was successfully updated.');
    }
}
