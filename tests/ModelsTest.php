<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\TeamsServiceProvider;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\CustomAbility;
use Jurager\Teams\Tests\Fixtures\CustomPermission;
use Jurager\Teams\Tests\Fixtures\CustomRole;
use Jurager\Teams\Tests\Fixtures\CustomTeam;
use Jurager\Teams\Tests\Fixtures\User;

it('uses configured subclasses everywhere', function () {
    config([
        'teams.models.role' => CustomRole::class,
        'teams.models.ability' => CustomAbility::class,
        'teams.models.permission' => CustomPermission::class,
    ]);
    $team = team();
    $member = $team->member(member($team, ['articles.view']));
    $article = article($team);
    $member->allow('articles.edit')->on($article);

    expect($member->role())->toBeInstanceOf(CustomRole::class)
        ->and($team->permissions()->first())->toBeInstanceOf(CustomPermission::class)
        ->and($team->abilities()->first())->toBeInstanceOf(CustomAbility::class)
        ->and($member->can('articles.view'))->toBeTrue()
        ->and($member->can('articles.edit', $article))->toBeTrue();
});

it('requires configured models to extend the package models', function (string $model, string $class) {
    config(["teams.models.{$model}" => $class]);

    expect(fn () => Teams::model($model))
        ->toThrow(RuntimeException::class, "[teams.models.{$model}] must be a class that extends");
})->with([
    'role' => ['role', Article::class],
    'membership' => ['membership', Article::class],
    'missing class' => ['team', 'missing'],
]);

it('keeps the new defaults with a partial published config', function () {
    config(['teams.tables' => ['teams' => 'teams', 'team_user' => 'team_user']]);
    config(['teams.models' => ['user' => User::class]]);

    (new TeamsServiceProvider($this->app))->register();

    expect(config('teams.tables.entity_ability'))->toBe('entity_ability')
        ->and(Teams::model('role'))->toBe(Role::class)
        ->and(Teams::model('user'))->toBe(User::class);
});

it('keeps events and cleanup for subclasses that override booted', function () {
    config(['teams.models.team' => CustomTeam::class, 'teams.models.role' => CustomRole::class]);
    Event::fake([TeamCreated::class]);
    $team = CustomTeam::create(['user_id' => user()->id, 'name' => 'Acme']);
    $role = $team->roles()->create(['code' => 'content-editor'])->grant('articles.edit');

    Event::assertDispatchedTimes(TeamCreated::class, 1);
    expect($role->name)->toBe('Content Editor');

    $role->delete();

    $this->assertDatabaseMissing(config('teams.tables.entity_permission'), ['entity_id' => $role->id]);
});

it('rejects duplicate rows in the database', function (Closure $insert) {
    $team = team();
    $user = member($team);
    $team->groups()->create(['code' => 'editors'])->add($user);
    $team->member($user)->allow('articles.edit')->on(article($team));

    expect(fn () => $insert($team, $user))->toThrow(QueryException::class);
})->with([
    'group member' => fn ($team, $user) => $team->group('editors')->members()->attach($user),
    'ability' => fn ($team) => DB::table(config('teams.tables.abilities'))
        ->insert(collect($team->abilities()->first()->getAttributes())->except('id')->all()),
    'record rule' => fn () => DB::table(config('teams.tables.entity_ability'))
        ->insert(collect((array) DB::table(config('teams.tables.entity_ability'))->first())->except('id')->all()),
]);
