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
        Schema::create('downvote_excluded_communities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('upvote_id');
            $table->text('list');
            $table->timestamps();

            $table->index('upvote_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downvote_excluded_communities');
    }
};
