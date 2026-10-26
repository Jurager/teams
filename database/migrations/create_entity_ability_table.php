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
        Schema::create(Config::get('teams.tables.entity_ability', 'entity_ability'), static function (Blueprint $table) {
            $table->id();
            $table->foreignId('ability_id')->constrained(Config::get('teams.tables.abilities'))->cascadeOnDelete();
            $table->morphs('entity');
            $table->boolean('forbidden');
            $table->timestamps();

            $table->unique(['ability_id', 'entity_type', 'entity_id'], 'teams_entity_ability_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Config::get('teams.tables.entity_ability', 'entity_ability'));
    }
};
