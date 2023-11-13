<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Curation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
