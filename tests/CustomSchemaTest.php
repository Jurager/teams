<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\TestCase;

beforeAll(function () {
    $tables = (require __DIR__.'/../config/teams.php')['tables'];

    TestCase::configure([
        'teams.tables' => array_map(fn (string $table) => 'custom_'.$table, $tables),
        'teams.foreign_keys.team_id' => 'workspace_id',
    ]);
});

it('uses the configured tables and foreign key everywhere', function () {
    Mail::fake();
    $team = team();
    $user = member($team, ['articles.view']);
    $member = $team->member($user);
    $role = $member->role();

    expect($role->team->is($team))->toBeTrue()
        ->and($role->members->first()->is($user))->toBeTrue();

    $group = $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    $article = article($team);
    $member->allow('articles.publish')->on($article);

    expect($member->can('articles.edit'))->toBeTrue()
        ->and($member->can('articles.publish', $article))->toBeTrue()
        ->and($user->can('articles.publish', $article))->toBeTrue()
        ->and($team->abilities()->first()->team->is($team))->toBeTrue()
        ->and($team->invite('custom@example.com')->as($role)->team->is($team))->toBeTrue()
        ->and($user->memberships()->first()->membership->getTable())->toBe('custom_team_user');

    expect($user->accessible()->first()->is($team))->toBeTrue()
        ->and($team->rules($article)->first()->users->first()->is($user))->toBeTrue();

    $member->forget('articles.publish')->on($article);
    $ability = $team->abilities()->firstOrFail();
    expect($ability->prunable()->count())->toBe(1)
        ->and($ability->prune())->toBeTrue();

    $team->remove($user);
    $group->delete();
    $role->delete();
    $team->purge();

    expect(Team::count())->toBe(0);
});

it('rolls back the migrations with the configured tables', function () {
    foreach ([
        'create_invitations_table', 'create_entity_permission_table', 'create_group_user_table',
        'create_groups_table', 'create_entity_ability_table', 'create_abilities_table',
        'create_team_user_table', 'create_roles_table', 'create_permissions_table', 'create_teams_table',
    ] as $migration) {
        (require __DIR__."/../database/migrations/{$migration}.php")->down();
    }

    foreach (config('teams.tables') as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

it('requires the configured team key when checking a selected record', function () {
    $team = team();
    $member = $team->member(member($team, ['articles.*']));
    $article = article($team);

    expect($member->can('articles.edit', Article::select('id', 'workspace_id')->findOrFail($article->id)))->toBeTrue()
        ->and($member->can('articles.edit', Article::select('id')->findOrFail($article->id)))->toBeFalse();
});
