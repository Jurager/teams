<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Tests\Fixtures\OwnedArticle;

it('explains the deciding source without changing precedence', function (string $source, string $code, bool $allowed, bool $preload) {
    $team = team();
    $user = member($team);
    $member = $team->member($user);
    $article = article($team);
    $role = $member->role();
    $group = $team->groups()->create(['code' => 'editors'])->add($user);

    match ($source) {
        'user allow' => $member->allow('articles.*')->on($article),
        'user forbid' => $member->forbid('articles.edit')->on($article),
        'group allow' => $group->allow('articles.*')->on($article),
        'group forbid' => $group->forbid('articles.edit')->on($article),
        'group permission' => $group->grant('articles.*'),
        'role allow' => $role->allow('articles.*')->on($article),
        'role forbid' => $role->forbid('articles.edit')->on($article),
        'role permission' => $role->grant('articles.*'),
        'global group' => Group::create(['code' => 'support'])->grant('articles.*')->add($user),
        'no grant' => null,
    };

    if ($preload) {
        $member->load([$article]);
        DB::enableQueryLog();
    }

    $response = $member->inspect('articles.edit', $article);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->allowed())->toBe($allowed)
        ->and($response->code())->toBe($code)
        ->and($response->message())->not->toBeEmpty()
        ->and($member->can('articles.edit', $article))->toBe($allowed);

    if ($preload) {
        expect(DB::getQueryLog())->toBeEmpty();
    }
})->with([
    ['user allow', 'user_allowed', true],
    ['user forbid', 'user_forbidden', false],
    ['group allow', 'group_allowed', true],
    ['group forbid', 'group_forbidden', false],
    ['group permission', 'group_permission', true],
    ['role allow', 'role_allowed', true],
    ['role forbid', 'role_forbidden', false],
    ['role permission', 'role_permission', true],
    ['global group', 'global_group', true],
    ['no grant', 'missing_permission', false],
])->with([false, true]);

it('explains ownership, membership and team boundaries', function () {
    $team = team();
    $user = user();
    $article = OwnedArticle::create(['team_id' => $team->id, 'owner_id' => $user->id]);

    expect($team->member($team->owner)->inspect('anything')->code())->toBe('team_owner')
        ->and($team->member($team->owner)->inspect('anything', article(team()))->code())->toBe('team_mismatch')
        ->and($team->member($user)->inspect('anything', $article)->code())->toBe('record_owner')
        ->and($team->member($user)->inspect('anything')->code())->toBe('not_member')
        ->and($team->member($user)->inspect('anything', article($team))->code())->toBe('not_member');
});

it('explains team-wide role and group permissions', function () {
    $team = team();
    $user = member($team, ['articles.view']);
    $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    $member = $team->member($user);

    expect($member->inspect('articles.view')->code())->toBe('role_permission')
        ->and($member->inspect('articles.edit')->code())->toBe('group_permission')
        ->and($member->inspect('articles.delete')->code())->toBe('missing_permission');
});

it('loads team permissions once when checking several codes', function () {
    $team = team();
    $user = member($team, ['articles.view']);
    $team->groups()->create(['code' => 'editors'])->grant('articles.edit', 'articles.publish')->add($user);
    DB::enableQueryLog();

    expect($team->member($user)->can(['articles.view', 'articles.edit', 'articles.publish']))->toBeTrue()
        ->and(DB::getQueryLog())->toHaveCount(4);
});

it('exposes explanations through the Gate and authorization exceptions', function () {
    $team = team();
    $user = member($team, ['articles.*']);
    $article = article($team);
    $team->member($user)->forbid('articles.edit')->on($article);
    Gate::define('articles.view', fn ($user, $record) => $team->member($user)->inspect('articles.view', $record));
    Gate::define('articles.edit', fn ($user, $record) => $team->member($user)->inspect('articles.edit', $record));
    $gate = Gate::forUser($user);

    expect($gate->inspect('articles.view', $article)->code())->toBe('role_permission')
        ->and($gate->inspect('articles.edit', $article)->code())->toBe('user_forbidden')
        ->and(fn () => $gate->authorize('articles.edit', $article))
        ->toThrow(AuthorizationException::class, 'Forbidden by a personal rule.');
});

it('preserves application authorization responses', function (bool $allowed) {
    $team = team();
    Gate::define('articles.edit', fn () => $allowed
        ? Response::allow('Application grant.', 'application')
        : Response::deny('Application denial.', 'application'));

    $response = Gate::forUser($team->owner)->inspect('articles.edit', $team);

    expect($response->allowed())->toBe($allowed)
        ->and($response->code())->toBe('application')
        ->and($response->message())->toBe($allowed ? 'Application grant.' : 'Application denial.');
})->with([false, true]);
