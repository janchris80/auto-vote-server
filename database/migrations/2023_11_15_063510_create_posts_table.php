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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->mediumText('user');
            $table->string('title', 255);
            $table->mediumText('content');
            $table->bigInteger('date')->nullable();
            $table->string('maintag', 255)->nullable();
            $table->text('json')->nullable();
            $table->mediumText('permlink');
            $table->integer('status')->default(0);
            $table->integer('upvote')->default(0);
            $table->integer('rewards')->default(0);
            $table->timestamps();

            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
};
