<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurationResource extends JsonResource
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
            "username" => $this->author,
            "userId" => $this->followedUser->id,
            "followersCount" => $this->followers_count,
            'weight' => $this->voter_weight,
            'votingType' => $this->voting_type,
            'isEnable' => $this->is_enable,
            'votingTime' => $this->voting_time,
            'excludedCommunities' => $this->excludedCommunities,
        ];
    }
}
