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
        Schema::create('upvote_laters', function (Blueprint $table) {
            $table->id();
            $table->string('voter', 255);
            $table->string('author', 255);
            $table->text('permlink');
            $table->integer('weight')->nullable();
            $table->bigInteger('time')->nullable();
            $table->integer('trail_fan')->default(0);
            $table->string('trailer', 255);
            $table->timestamps();

            $table->index('time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('upvote_laters');
    }
};
