<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'enable',
        'added',
        'claim_reward',
        'current_power',
        'limit_power',
        'sp',
        'paused',
        'discord_webhook_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function trailers()
    {
        return $this->hasMany(Trailer::class, 'user_id');
    }

    public function curationTrailer()
    {
        return $this->hasOne(Trailer::class, 'user_id')
            ->where('type', '=', 'curation');
    }

    public function downvoteTrailer()
    {
        return $this->hasOne(Trailer::class, 'user_id')
            ->where('type', '=', 'downvote');
    }

    public function followers()
    {
        return $this->hasMany(Follower::class, 'user_id');
    }

    public function follower()
    {
        return $this->hasOne(Follower::class, 'user_id');
    }

    public function followersCount()
    {
        return $this->hasMany(Follower::class, 'user_id')
            ->selectRaw('user_id, count(*) as count')
            ->groupBy('user_id');
    }

    public function followings()
    {
        return $this->hasMany(Follower::class, 'follower_id');
    }

    public function followingsCount()
    {
        return $this->hasMany(Follower::class, 'follower_id')
            ->selectRaw('follower_id, count(*) as count')
            ->groupBy('follower_id');
    }

    public function followingsCurationCount()
    {
        return $this->hasMany(Follower::class, 'follower_id')
            ->where('follower_type', '=', 'curation')
            ->selectRaw('follower_id, count(*) as count')
            ->groupBy('follower_id');
    }

    public function followingsDownvoteCount()
    {
        return $this->hasMany(Follower::class, 'follower_id')
            ->where('follower_type', '=', 'downvote')
            ->selectRaw('follower_id, count(*) as count')
            ->groupBy('follower_id');
    }

    public function getIsFollowedByCurrentUserAttribute()
    {
        if (auth()->check()) {
            return Follower::where('user_id', $this->id)
                ->where('follower_id', auth()->id())
                ->exists();
        }

        return false;
    }
}
