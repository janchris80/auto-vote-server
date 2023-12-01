<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PopularResource extends JsonResource
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
            'id' => (string)$this->id,
            'username' => $this->user->username,
            'description' => $this->description,
            'followersCount' => optional($this->user)->followers_count_count,
            'isFollowed' => optional($this->user)->isFollowed,
            // 'followers' => FollowerResource::collection($this->followings),
        ];
    }
}
