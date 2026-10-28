<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Config::get('teams.tables.entity_ability'), static function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(Config::get('teams.tables.entity_ability'), static function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
