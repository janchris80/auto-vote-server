<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "username" => $this->commenter,
            "userId" => $this->user->id,
            // "followersCount" => $this->follower()->count(),
            'weight' => $this->voter_weight,
            'isEnable' => $this->is_enable,
        ];
    }
}
