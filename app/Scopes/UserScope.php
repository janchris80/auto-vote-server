<?php
// FollowerFunctionsTrait.php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait UserScope
{
    public function scopeWhereHasFollower(Builder $query, $userId, $followerType)
    {
        $query->whereHas('follower', function ($query) use ($userId, $followerType) {
            $query->where('follower_id', $userId)
                ->where('follower_type', $followerType);
        });
    }

    public function scopeWhereHasTrailer(Builder $query, $followerType)
    {
        $query->whereHas('trailer', function ($query) use ($followerType) {
            $query->where('type', '=', $followerType);
        });
    }

    public function scopeWithFollower(Builder $query, $userId, $followerType)
    {
        $query->with([
            'follower' => function ($query) use ($userId, $followerType) {
                $query->where('follower_id', $userId)
                    ->where('follower_type', $followerType);
            },
        ]);
    }
}
