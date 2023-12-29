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
        'last_voted_at',
    ];
}
