<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpvotePostExcludedCommunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'upvote_id',
        'list',
    ];
}
