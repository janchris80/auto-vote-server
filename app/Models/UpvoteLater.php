<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UpvoteLater extends Model
{
    use HasFactory;

    protected $fillable = [
        'voter',
        'author',
        'permlink',
        'weight',
        'time_to_vote',
    ];
}
