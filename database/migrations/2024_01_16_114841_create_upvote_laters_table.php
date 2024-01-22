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
        Schema::create('upvote_laters', function (Blueprint $table) {
            $table->id();
            $table->string('author', 16);
            $table->string('voter', 16);
            $table->text('permlink');
            $table->unsignedBigInteger('weight');
            $table->timestamp('time_to_vote');
            $table->timestamps();

            $table->index('author');
            $table->index('voter');
            $table->index('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upvote_laters');
    }
};
