<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbilitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('teams.tables.abilities', 'abilities'), function (Blueprint $table) {
            $table->id();
	        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
	        $table->string('name');
	        $table->string('title');
	        $table->string('entity_id');
	        $table->string('entity_type');
	        $table->boolean('only_owned');
	        $table->text('options');
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
        Schema::dropIfExists(config('teams.tables.abilities', 'abilities'));
    }
}
