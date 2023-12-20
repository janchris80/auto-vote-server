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
        Schema::table('followers', function (Blueprint $table) {
            $table->timestamp('last_voted_at')->default(now());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('followers', function (Blueprint $table) {
            $table->dropColumn('last_voted_at');
        });
    }
};
