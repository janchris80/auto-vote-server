<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id"=> $this->id,
            "username" => $this->username,
            "limitPower" => $this->limit_power,
            "paused" => $this->paused,
            "enable" => $this->enable,
            "claimReward" => $this->claim_reward,
            // "curation" => new TrailerResource($this->curationTrailer), // get the type of curation
            // "downvote" => new TrailerResource($this->downvoteTrailer), // get the type of downvote
            "authorizeAccount" => config('hive.account'),
        ];
    }
}
