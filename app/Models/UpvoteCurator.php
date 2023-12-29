<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpvoteCurator extends Model
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
}
