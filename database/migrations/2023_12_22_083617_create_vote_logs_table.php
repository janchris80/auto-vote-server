<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vote_logs', function (Blueprint $table) {
            $table->id();
            $table->string('voter', 16);
            $table->string('author', 16);
            $table->string('permlink');
            $table->string('followed_author', 16);
            $table->unsignedSmallInteger('author_weight');
            $table->unsignedSmallInteger('voter_weight');
            $table->unsignedSmallInteger('mana_left');
            $table->unsignedSmallInteger('rc_left');
            $table->unsignedSmallInteger('limit_mana');
            $table->enum('trailer_type', ['curation', 'downvote', 'upvote_comment', 'upvote_post']);
            $table->enum('voting_type', ['scaled', 'fixed']);
            $table->timestamp('voted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vote_logs');
    }
};
