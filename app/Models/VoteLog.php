<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'voter',
        'author',
        'permlink',
        'followed_author',
        'author_weight',
        'voter_weight',
        'mana_left',
        'rc_left',
        'trailer_type',
        'voting_type',
        'limit_mana',
        'voted_at',
    ];

    protected $casts = [
        'author_weight' => 'integer',
        'voter_weight' => 'integer',
        'mana_left' => 'integer',
        'rc_left' => 'integer',
        'limit_mana' => 'integer',
        'voted_at' => 'datetime',
    ];
}
