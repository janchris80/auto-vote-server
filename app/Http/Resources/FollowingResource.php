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
            "id" => $this->user->follower->id,
            "username" => $this->user->username,
            "userId" => $this->user->id,
            "followersCount"=> $this->user->followersCount()->count(),
            'weight' => $this->user->follower->weight,
            'votingType' => $this->user->follower->voting_type,
            'afterMin' => $this->user->follower->after_min,
            'dailyLimit' => $this->user->follower->daily_limit,
            'limitLeft' => $this->user->follower->limit_left,
            'isEnable' => $this->user->follower->enable,
        ];
    }
}
