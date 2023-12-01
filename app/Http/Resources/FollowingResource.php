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
            "id" => $this->id,
            "username" => $this->user->username,
            "followersCount"=> $this->user->followersCount()->count(),
            'weight' => $this->user->follower->weight,
            'method' => $this->user->follower->voting_type,
            'waitTime' => $this->user->follower->after_min,
            'status' => $this->user->follower->enable,
        ];
    }
}
