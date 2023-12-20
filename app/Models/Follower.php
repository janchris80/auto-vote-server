<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Follower extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'follower_id',
        'voting_type', // scaled, fixed = method
        'trailer_type', // curation, downvote, upvote_comment, upvote_post
        'weight',
        'vote_per_day',
        'vote_per_week',
        'is_enable',
        'is_being_processed',
        'last_voted_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }
}
