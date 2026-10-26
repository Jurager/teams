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
        Schema::create(Config::get('teams.tables.entity_permission', 'entity_permission'), static function (Blueprint $table) {
            $table->id();
            $table->morphs('entity');
            $table->foreignId('permission_id')->constrained(Config::get('teams.tables.permissions'))->cascadeOnDelete();

            $table->unique(['permission_id', 'entity_type', 'entity_id'], 'teams_entity_permission_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Config::get('teams.tables.entity_permission', 'entity_permission'));
    }
};
