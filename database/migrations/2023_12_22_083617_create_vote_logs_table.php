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
            $table->string('voter');
            $table->string('author');
            $table->string('permlink');
            $table->string('author_weight');
            $table->integer('voter_weight');
            $table->integer('mana_left');
            $table->integer('rc_left');
            $table->string('trailer_type');
            $table->string('voting_type');
            $table->integer('limit_mana');
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
