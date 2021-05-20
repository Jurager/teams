<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('teams.tables.permissions', 'permissions'), function (Blueprint $table) {
            $table->id();
	        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
	        $table->integer('ability_id');
	        $table->string('entity_id');
	        $table->string('entity_type');
	        $table->boolean('forbidden');
	        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('teams.tables.permissions', 'permissions'));
    }
}