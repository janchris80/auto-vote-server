<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowingResource extends JsonResource
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
            "id" => $this->follower->id,
            "username" => $this->username,
            "userId" => $this->id,
            "followersCount" => $this->followersCount()->count(),
            'weight' => $this->follower->weight,
            'votingType' => $this->follower->voting_type,
            'isEnable' => $this->follower->is_enable,
        ];
    }
}
