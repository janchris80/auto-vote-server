<?php
// UserScope.php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait UserScope
{
    public function scopeWhereHasFollower(Builder $query, $userId, $followerType)
    {
        return $query->whereHas('follower', function ($query) use ($userId, $followerType) {
            $query->where('follower_id', $userId)
                ->where('trailer_type', $followerType);
        });
    }

    public function scopeWhereHasTrailer(Builder $query, $followerType)
    {
        return $query->whereHas('trailer', function ($query) use ($followerType) {
            $query->where('trailer_type', '=', $followerType);
        });
    }

    public function scopeWithFollower(Builder $query, $userId, $followerType)
    {
        return $query->with([
            'follower' => function ($query) use ($userId, $followerType) {
                $query->where('follower_id', $userId)
                    ->where('trailer_type', $followerType);
            },
        ]);
    }
}
