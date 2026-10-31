<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $schema = $connection->getSchemaBuilder();
        $abilities = Config::get('teams.tables.abilities', 'abilities');
        $assignments = Config::get('teams.tables.entity_ability', 'entity_ability');
        $temporary = $abilities.'_new';
        $teamKey = Config::get('teams.foreign_keys.team_id', 'team_id');

        if ($schema->hasTable($temporary)) {
            throw new RuntimeException("Table [{$temporary}] already exists; inspect it before retrying the migration.");
        }

        $schema->create($temporary, function (Blueprint $table) use ($teamKey) {
            $table->id();
            $table->foreignId($teamKey)->constrained(Config::get('teams.tables.teams'))->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained(Config::get('teams.tables.permissions'))->cascadeOnDelete();
            $table->morphs('entity');
            $table->morphs('recipient');
            $table->boolean('forbidden')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique([$teamKey, 'permission_id', 'entity_type', 'entity_id', 'recipient_type', 'recipient_id'], 'teams_ability_recipient_unique');
        });

        $columns = [
            $teamKey => "definitions.{$teamKey}",
            'permission_id' => 'definitions.permission_id',
            'entity_type' => 'definitions.entity_type',
            'entity_id' => 'definitions.entity_id',
            'recipient_type' => 'assignments.entity_type',
            'recipient_id' => 'assignments.entity_id',
            'forbidden' => 'assignments.forbidden',
            'expires_at' => 'assignments.expires_at',
            'created_at' => 'assignments.created_at',
            'updated_at' => 'assignments.updated_at',
        ];

        $rows = $connection->table($assignments.' as assignments')
            ->join($abilities.' as definitions', 'definitions.id', '=', 'assignments.ability_id')
            ->select(array_values($columns));

        $connection->table($temporary)->insertUsing(array_keys($columns), $rows);

        if ($connection->table($temporary)->count() !== $connection->table($assignments)->count()) {
            throw new RuntimeException('Ability assignment counts differ; the original tables have been preserved.');
        }

        $schema->drop($assignments);
        $schema->drop($abilities);
        $schema->rename($temporary, $abilities);
    }
};
