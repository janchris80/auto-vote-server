<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpvotedComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'voter',
        'author',
        'permlink',
        'weight',
    ];
    
}
