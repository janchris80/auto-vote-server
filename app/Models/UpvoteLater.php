<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UpvoteLater extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'voter',
        'author',
        'permlink',
        'weight',
        'time_to_vote',
    ];

    // Set default values for attributes
    protected $attributes = [
        'uuid' => null,  // Use null or provide a default UUID here
    ];

    // If you want to generate a UUID automatically when creating a new instance
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid();
        });
    }
}
