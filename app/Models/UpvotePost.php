<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpvotePost extends Model
{
    use HasFactory;

    protected $fillable = [
        'author',
        'voter',
        'voter_weight',
        'is_enable',
        'voting_type',
        'last_voted_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'voter', 'username');
    }

    public function followedUser()
    {
        return $this->belongsTo(User::class, 'author', 'username');
    }

    public function excludedCommunities()
    {
        return $this->hasMany(UpvotePostExcludedCommunity::class, 'upvote_id', 'id');
    }
}
