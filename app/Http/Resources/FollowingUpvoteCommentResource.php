<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowingUpvoteCommentResource extends JsonResource
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
            // "followersCount" => $this->follower()->count(),
            'weight' => $this->follower->weight,
        ];
    }
}
