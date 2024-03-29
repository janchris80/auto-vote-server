<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('followers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // user_id
            $table->unsignedBigInteger('follower_id'); // user_id
            $table->string('voting_type')->default(null); // [fixed, scaled] for curation and downvote only
            $table->string('trailer_type'); // [curation, downvote, fanbase]
            $table->integer('weight')->default(0);
            $table->integer('vote_per_day')->default(5); // vote per day
            $table->integer('vote_per_week')->default(5); // total vote per week
            $table->boolean('is_enable')->default(true);
            $table->boolean('is_being_processed')->default(false);
            $table->timestamps();

            $table->foreign('follower_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('followers');
    }
};
