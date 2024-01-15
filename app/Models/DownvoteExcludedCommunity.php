<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownvoteExcludedCommunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'upvote_id',
        'list',
    ];
}
