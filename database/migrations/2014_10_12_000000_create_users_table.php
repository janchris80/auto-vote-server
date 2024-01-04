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
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->integer('limit_upvote_mana')->default(8000);
            $table->integer('limit_downvote_mana')->default(8000);
            $table->boolean('is_auto_claim_reward')->default(false);
            $table->boolean('is_enable')->default(false);
            $table->boolean('is_pause')->default(false);
            $table->text('discord_webhook_url')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
