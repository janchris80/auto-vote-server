<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
