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
            $table->string('user', 255);
            $table->string('commenter', 255);
            $table->integer('weight')->default(10000);
            $table->integer('aftermin')->default(0);
            $table->integer('enable')->default(1);
            $table->integer('todayvote')->default(0);
            $table->timestamps();

            $table->index('enable');
            $table->index('todayvote');
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
