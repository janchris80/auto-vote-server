<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpvoteComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'author',
        'commenter',
        'voter_weight',
        'is_enable',
        'voting_type',
        'voting_time',
        'last_voted_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'author', 'username');
    }

    public function followedUser()
    {
        return $this->belongsTo(User::class, 'commenter', 'username');
    }

    public function excludedCommunities()
    {
        return $this->hasMany(UpvoteCommentExcludedCommunity::class, 'upvote_id', 'id');
    }
}
