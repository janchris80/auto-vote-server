<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user',
        'title',
        'content',
        'date',
        'maintag',
        'json',
        'permlink',
        'status',
        'upvote',
        'rewards',
    ];
}
