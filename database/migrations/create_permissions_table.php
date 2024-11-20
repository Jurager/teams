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
        Schema::create('permissions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId(Config::get('teams.foreign_keys.team_id', 'team_id'))->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('code');

            $table->unique([Config::get('teams.foreign_keys.team_id', 'team_id'), 'code']);
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
