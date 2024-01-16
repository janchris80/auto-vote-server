<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            "id" => $this->id,
            "username" => $this->author,
            "userId" => $this->followedUser->id,
            'weight' => $this->voter_weight,
            'isEnable' => $this->is_enable,
            'votingTime' => $this->voting_time,
            'excludedCommunities' => $this->excludedCommunities,
        ];
    }
}
