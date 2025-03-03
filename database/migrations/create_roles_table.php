<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', static function (Blueprint $table) {
            $table->id();
            $table->foreignId(Config::get('teams.foreign_keys.team_id', 'team_id'))->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->string('description')->nullable();

            $table->unique([Config::get('teams.foreign_keys.team_id', 'team_id'), 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
