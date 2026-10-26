<?php

use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Permission;
use Jurager\Teams\Tests\Fixtures\ScopedGroup;
use Jurager\Teams\Tests\Fixtures\ScopedRole;
use Jurager\Teams\Tests\Fixtures\ScopedUser;

it('grants, revokes and syncs permissions', function () {
    $team = team();
    $role = role($team, 'editor');

    $role->grant('articles.view', 'articles.edit')->grant(['articles.edit']);
    expect($role->permissions->pluck('code')->all())->toEqualCanonicalizing(['articles.view', 'articles.edit']);

    $role->revoke('articles.edit', 'missing');
    expect($role->permissions->pluck('code')->all())->toBe(['articles.view']);

    $role->sync('articles.*');
    expect($role->permissions->pluck('code')->all())->toBe(['articles.*']);

    $role->sync();
    expect($role->permissions)->toBeEmpty()
        ->and($team->permissions()->count())->toBe(3);
});

it('deduplicates and reuses permission codes', function () {
    $team = team();
    $ids = Permission::ids($team->id, ['articles.edit', 'articles.edit']);

    expect($ids)->toHaveCount(1)
        ->and(Permission::ids($team->id, ['articles.edit']))->toBe($ids)
        ->and(Permission::ids($team->id, []))->toBe([])
        ->and($team->permissions()->count())->toBe(1);
});

it('gives a member the permissions of their role and team groups', function () {
    $team = team();
    $other = team();
    $user = member($team, ['articles.view']);
    $team->groups()->create(['code' => 'publishers'])->grant('articles.publish')->add($user);
    role($other, 'admin', ['other.*']);
    $other->add($user)->as('admin');
    $other->groups()->create(['code' => 'publishers'])->grant('other.publish')->add($user);

    expect($team->member($user)->permissions())->toEqualCanonicalizing(['articles.view', 'articles.publish']);
});

it('matches wildcards with all or any semantics', function (string $method, string|array $permissions, bool $expected) {
    $team = team();
    $user = member($team, ['articles.*']);
    $team->groups()->create(['code' => 'reporters'])->grant('reports.view')->add($user);

    expect($team->member($user)->{$method}($permissions))->toBe($expected);
})->with([
    'wildcard' => ['can', 'articles.edit', true],
    'nested wildcard' => ['can', 'articles.comments.edit', true],
    'different prefix' => ['can', 'articles2.edit', false],
    'parent is not a descendant' => ['can', 'articles', false],
    'group permission' => ['can', 'reports.view', true],
    'all granted' => ['can', ['articles.edit', 'reports.view'], true],
    'one missing' => ['can', ['articles.edit', 'missing'], false],
    'any granted' => ['canAny', ['articles.edit', 'missing'], true],
    'none granted' => ['canAny', ['missing', 'other'], false],
    'empty all' => ['can', [], false],
    'empty any' => ['canAny', [], false],
]);

it('lets the owner do everything and strangers nothing', function () {
    $team = team();

    expect($team->member($team->owner)->can(['articles.edit', 'anything']))->toBeTrue()
        ->and($team->member(user())->can('articles.edit'))->toBeFalse();
});

it('treats wildcard nodes as literal codes until enabled', function (string $node, array $config) {
    $team = team();
    $member = $team->member(member($team, [$node]));
    $article = article($team);

    expect($member->can('articles.edit'))->toBeFalse()
        ->and($member->can('articles.edit', $article))->toBeFalse();

    config($config);

    expect($member->can('articles.edit'))->toBeTrue()
        ->and($member->can('articles.edit', $article))->toBeTrue();
})->with([
    'default node' => ['*', ['teams.wildcards.enabled' => true]],
    'configured node' => ['all', ['teams.wildcards.enabled' => true, 'teams.wildcards.nodes' => ['all']]],
]);

it('lets global groups grant record permissions in every team', function () {
    $team = team();
    $user = user();
    $article = article($team);
    $group = Group::create(['code' => 'support'])->grant('articles.*')->add($user);

    expect(Permission::whereNull('team_id')->count())->toBe(1)
        ->and($user->in($team))->toBeFalse()
        ->and($team->member($user)->can('articles.edit', $article))->toBeTrue()
        ->and($team->member($user)->can('articles.edit'))->toBeFalse();

    $group->remove($user);

    expect($team->member($user)->can('articles.edit', $article))->toBeFalse();
});

it('keeps custom relationship constraints', function () {
    config([
        'teams.models.user' => ScopedUser::class,
        'teams.models.role' => ScopedRole::class,
        'teams.models.group' => ScopedGroup::class,
    ]);
    $team = team();
    $user = ScopedUser::create(['email' => 'scoped@example.com']);
    role($team, 'editor', ['visible.role', 'hidden.role']);
    $team->add($user)->as('editor');
    $team->groups()->create(['code' => 'visible'])->grant('visible.group', 'hidden.group.permission')->add($user);
    $team->groups()->create(['code' => 'hidden'])->grant('hidden.group')->add($user);
    $member = $team->member($user);

    expect($member->permissions())->toEqualCanonicalizing(['visible.role', 'visible.group'])
        ->and($member->canAny(['hidden.role', 'hidden.group', 'hidden.group.permission']))->toBeFalse();
});
