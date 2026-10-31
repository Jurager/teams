<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;
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
        ->and($team->rules($article)->first()->recipient->is($user))->toBeTrue();

    $member->forget('articles.publish')->on($article);
    expect($team->abilities()->count())->toBe(0);

    $team->remove($user);
    $group->delete();
    $role->delete();
    $team->purge();

    expect(Team::count())->toBe(0);
});

it('uses only configured table names', function () {
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

it('queries permissions, batches members and expires rules with the configured schema', function () {
    $this->freezeTime();
    $team = team();
    role($team, 'reader', ['articles.view']);
    $users = collect([user(), user()]);
    $team->add($users)->as('reader');
    $article = article($team);
    $member = $team->member($users->first());
    $member->forbid('articles.view')->until(now()->addMinute())->on($article);
    $member->load([$article]);

    expect(User::permission('articles.view', $team)->get()->modelKeys())->toEqualCanonicalizing([$team->user_id, ...$users->map->getKey()->all()])
        ->and($member->can('articles.view', $article))->toBeFalse();

    $this->travel(1)->minutes();
    expect($member->can('articles.view', $article))->toBeTrue();

    $team->remove($users);
    expect($team->members()->count())->toBe(0);
});

it('uses configured tables and the team key for external rules', function () {
    $team = team();
    $member = $team->member(member($team));
    $member->allow('products.update')->on('catalog.products', 42);
    $rule = $team->rules('catalog.products', 42)->firstOrFail();

    expect($rule->getTable())->toBe('custom_abilities')
        ->and($rule->workspace_id)->toBe($team->id)
        ->and($rule->recipient->is($member->user))->toBeTrue()
        ->and($member->inspect('products.update', 'catalog.products', 42)->code())->toBe('user_allowed');
});
