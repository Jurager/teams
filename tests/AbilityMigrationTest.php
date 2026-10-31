<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Tests\TestCase;

beforeAll(fn () => TestCase::configure([
    'teams.foreign_keys.team_id' => 'workspace_id',
    'teams.tables.abilities' => 'custom_abilities',
    'teams.tables.entity_ability' => 'custom_entity_ability',
]));

beforeEach(function () {
    Schema::drop(config('teams.tables.abilities'));

    foreach (['create_abilities_table', 'create_entity_ability_table', 'add_expires_at_to_entity_ability_table'] as $name) {
        (require __DIR__."/../database/migrations/{$name}.php")->up();
    }
});

function legacyAbility(Model $team, Model $record, string $code): int
{
    return DB::table(config('teams.tables.abilities'))->insertGetId([
        'workspace_id' => $team->getKey(),
        'permission_id' => $team->permissions()->create(['code' => $code])->getKey(),
        'entity_type' => $record->getMorphClass(), 'entity_id' => $record->getKey(),
        'created_at' => '2025-01-01 10:00:00', 'updated_at' => '2025-01-01 10:00:00',
    ]);
}

it('converts shared definitions to independent recipient rows and preserves assignment data', function () {
    $team = team();
    $article = article($team);
    $definition = legacyAbility($team, $article, 'articles.edit');
    $permissionId = $team->permissions()->where('code', 'articles.edit')->value('id');
    legacyAbility($team, $article, 'unused');
    $recipients = ['legacy-user' => false, 'legacy-role' => true, 'legacy-group' => false];

    foreach ($recipients as $type => $forbidden) {
        DB::table(config('teams.tables.entity_ability'))->insert([
            'ability_id' => $definition, 'entity_type' => $type, 'entity_id' => 42,
            'forbidden' => $forbidden, 'expires_at' => $forbidden ? null : '2030-01-01 00:00:00',
            'created_at' => '2026-01-01 12:00:00', 'updated_at' => '2026-02-01 13:00:00',
        ]);
    }

    (require __DIR__.'/../database/migrations/merge_ability_assignments.php')->up();

    $rows = DB::table(config('teams.tables.abilities'))->get()->keyBy('recipient_type');

    expect($rows)->toHaveCount(3)
        ->and(Schema::hasTable(config('teams.tables.entity_ability')))->toBeFalse();

    foreach ($recipients as $type => $forbidden) {
        $row = $rows[$type];
        expect($row->workspace_id)->toBe($team->getKey())
            ->and($row->permission_id)->toBe($permissionId)
            ->and($row->entity_id)->toBe($article->getKey())
            ->and($row->entity_type)->toBe($article->getMorphClass())
            ->and($row->recipient_id)->toBe(42)
            ->and((bool) $row->forbidden)->toBe($forbidden)
            ->and($row->expires_at)->toBe($forbidden ? null : '2030-01-01 00:00:00')
            ->and($row->created_at)->toBe('2026-01-01 12:00:00')
            ->and($row->updated_at)->toBe('2026-02-01 13:00:00');
    }

    $team->permissions()->delete();
    expect(DB::table(config('teams.tables.abilities'))->count())->toBe(0);
});

it('copies all assignments without dropping or duplicating recipients', function () {
    $team = team();
    $definition = legacyAbility($team, article($team), 'articles.edit');
    $other = legacyAbility($team, article($team), 'articles.delete');
    DB::table(config('teams.tables.entity_ability'))->insert(collect(range(1, 1001))->map(fn ($id) => [
        'ability_id' => $id % 2 === 1 ? $definition : $other,
        'entity_type' => 'user', 'entity_id' => $id, 'forbidden' => $id % 2 === 0,
    ])->all());

    (require __DIR__.'/../database/migrations/merge_ability_assignments.php')->up();

    expect(DB::table(config('teams.tables.abilities'))->count())->toBe(1001)
        ->and(DB::table(config('teams.tables.abilities'))->distinct()->count('recipient_id'))->toBe(1001)
        ->and($team->abilities()->whereHas('permission', fn ($query) => $query->where('code', 'articles.edit'))->count())->toBe(501)
        ->and($team->abilities()->whereHas('permission', fn ($query) => $query->where('code', 'articles.delete'))->count())->toBe(500);
});

it('refuses to overwrite an existing temporary table', function () {
    $temporary = config('teams.tables.abilities').'_new';
    Schema::create($temporary, fn ($table) => $table->id());

    expect(fn () => (require __DIR__.'/../database/migrations/merge_ability_assignments.php')->up())
        ->toThrow(RuntimeException::class, 'already exists')
        ->and(Schema::hasTable(config('teams.tables.entity_ability')))->toBeTrue()
        ->and(Schema::hasTable(config('teams.tables.abilities')))->toBeTrue();
});

it('preserves the original tables when a legacy assignment has no definition', function () {
    Schema::disableForeignKeyConstraints();
    DB::table(config('teams.tables.entity_ability'))->insert([
        'ability_id' => 999, 'entity_type' => 'user', 'entity_id' => 1, 'forbidden' => false,
    ]);
    Schema::enableForeignKeyConstraints();

    expect(fn () => (require __DIR__.'/../database/migrations/merge_ability_assignments.php')->up())
        ->toThrow(RuntimeException::class, 'counts differ')
        ->and(DB::table(config('teams.tables.entity_ability'))->count())->toBe(1)
        ->and(Schema::hasTable(config('teams.tables.abilities')))->toBeTrue();
});

it('adds expiration to legacy assignments without changing permanent rules', function () {
    $team = team();
    $definition = legacyAbility($team, article($team), 'articles.edit');
    $migration = require __DIR__.'/../database/migrations/add_expires_at_to_entity_ability_table.php';
    $migration->down();
    DB::table(config('teams.tables.entity_ability'))->insert([
        'ability_id' => $definition, 'entity_type' => 'user', 'entity_id' => 1, 'forbidden' => false,
    ]);
    $migration->up();

    expect(DB::table(config('teams.tables.entity_ability'))->first()->expires_at)->toBeNull();
});
