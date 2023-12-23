<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoteLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'voter' => $this->voter,
            'author'  => $this->author,
            'permlink'  => $this->permlink,
            'followedAuthor'  => $this->followed_author,
            'authorWeight'  => $this->author_weight,
            'voterWeight'  => $this->voter_weight,
            'upvoteManaLeft'  => $this->mana_left,
            'resourceCreditLeft'  => $this->rc_left,
            'trailerType'  => $this->trailer_type,
            'votingType'  => $this->voting_type,
            'manaThreshold'  => $this->limit_mana,
            'votedAt'  => $this->voted_at,
        ];
    }
}
