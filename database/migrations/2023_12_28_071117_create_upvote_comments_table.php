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
        Schema::create('upvote_comments', function (Blueprint $table) {
            $table->id();
            $table->string('author', 16);
            $table->string('commenter', 16);
            $table->unsignedSmallInteger('voter_weight')->default(5000);
            $table->boolean('is_enable');
            $table->enum('voting_type', ['scaled', 'fixed'])->default('fixed');

            $table->timestamp('last_voted_at')->default(now());
            $table->timestamps();

            $table->index('author');
            $table->index('commenter');
            $table->index('is_enable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upvote_comments');
    }
};
