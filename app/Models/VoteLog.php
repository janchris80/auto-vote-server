<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteLog extends Model
{
    use HasFactory;

    protected $filled = [
        'voter',
        'author',
        'permlink',
        'author_weight',
        'voter_weight',
        'mana_left',
        'rc_left',
        'trailer_type',
        'voting_type',
        'limit_mana',
        'voted_at',
    ];
}
