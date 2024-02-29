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
        Schema::create('team_user', static function (Blueprint $table) {
            $table->id();
            $table->foreignId(config('teams.foreign_keys.team_id', 'team_id'));
            $table->foreignId('user_id');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique([config('teams.foreign_keys.team_id', 'team_id'), 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
