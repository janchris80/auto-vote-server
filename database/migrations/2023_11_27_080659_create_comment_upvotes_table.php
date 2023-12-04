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
        Schema::create('comment_upvotes', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('commenter');
            $table->integer('weight')->default(10000);
            $table->boolean('enable')->default(true);
            $table->boolean('today_vote')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comment_upvotes');
    }
};
