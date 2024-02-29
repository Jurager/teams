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
        Schema::create('permissions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId(config('teams.foreign_keys.team_id', 'team_id'))->constrained()->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained()->cascadeOnDelete();
            $table->morphs('entity');
            $table->boolean('forbidden');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
