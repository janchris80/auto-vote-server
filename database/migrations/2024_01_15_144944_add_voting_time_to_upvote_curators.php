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
        Schema::table('upvote_curators', function (Blueprint $table) {
            $table->unsignedSmallInteger('voting_time')
            ->default(0)
            ->after('voting_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upvote_curators', function (Blueprint $table) {
            $table->dropColumn('voting_time');
        });
    }
};
