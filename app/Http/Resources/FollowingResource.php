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
            "id" => (string)$this->id,
            "username" => $this->username,
            "followers_count"=> $this->followers_count,
            "weight" => 50,
            "method" => 'Scale',
            "wait_time" => 0,
            "status" => false,
        ];
    }
}
