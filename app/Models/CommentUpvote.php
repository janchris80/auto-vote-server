<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentUpvote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user',
        'commenter',
        'weight',
        'aftermin',
        'enable',
        'todayvote',
    ];
}
