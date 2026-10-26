<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Config::get('teams.tables.abilities', 'abilities'), static function (Blueprint $table) {
            $table->id();
            $table->foreignId(Config::get('teams.foreign_keys.team_id', 'team_id'))->constrained(Config::get('teams.tables.teams'))->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained(Config::get('teams.tables.permissions'))->cascadeOnDelete();
            $table->morphs('entity');
            $table->timestamps();

            $table->unique([Config::get('teams.foreign_keys.team_id', 'team_id'), 'permission_id', 'entity_type', 'entity_id'], 'teams_abilities_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Config::get('teams.tables.abilities', 'abilities'));
    }
};
