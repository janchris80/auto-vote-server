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
            "limitUpvoteMana" => $this->limit_upvote_mana,
            "limitDownvoteMana" => $this->limit_downvote_mana,
            "isPause" => $this->is_pause,
            "isEnable" => $this->is_enable,
            "isAutoClaimReward" => $this->is_auto_claim_reward,
            "authorizeAccount" => config('hive.account'),
        ];
    }
}
