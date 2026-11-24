<?php

use Jurager\Teams\Models\Ability;

enum GrantCode: string
{
    case Edit = 'articles.edit';
}

it('lists the permission codes of a role or a group', function () {
    $team = team();
    $role = role($team, 'editor', ['articles.view', 'articles.edit']);
    $group = $team->groups()->create(['code' => 'publishers'])->grant('articles.publish');

    expect($role->codes())->toEqualCanonicalizing(['articles.view', 'articles.edit'])
        ->and($group->codes())->toBe(['articles.publish'])
        ->and(role($team, 'empty')->codes())->toBe([]);
});

it('lets a member grant only the permissions they hold', function (array $permissions, bool $expected) {
    $team = team();
    $member = $team->member(member($team, ['articles.view', 'comments.*']));

    expect($member->canGrant(...$permissions))->toBe($expected);
})->with([
    'held codes' => [['articles.view', 'comments.edit'], true],
    'an array of codes' => [[['articles.view', 'comments.delete']], true],
    'a code not held' => [['articles.view', 'articles.edit'], false],
    'a wildcard not held' => [['articles.*'], false],
    'nothing' => [[], true],
    'an empty array' => [[[]], true],
    'null' => [[null], true],
]);

it('lets a member grant roles and groups whose permissions they hold', function () {
    $team = team();
    $member = $team->member(member($team, ['articles.*']));
    $reader = role($team, 'reader', ['articles.view']);
    $admin = role($team, 'admin', ['articles.view', 'members.add']);
    $editors = $team->groups()->create(['code' => 'editors'])->grant('articles.edit');
    $managers = $team->groups()->create(['code' => 'managers'])->grant('members.add');

    expect($member->canGrant($reader))->toBeTrue()
        ->and($member->canGrant($reader, $editors))->toBeTrue()
        ->and($member->canGrant($admin))->toBeFalse()
        ->and($member->canGrant([$editors, $managers]))->toBeFalse()
        ->and($member->canGrant(role($team, 'empty')))->toBeTrue();
});

it('checks a record rule on its record', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    $member = $team->member($user);
    $article = article($team);
    $other = article($team);
    $colleague = $team->member(member($team));

    $colleague->forbid('articles.edit')->on($article);
    $member->forbid('articles.edit')->on($other);
    $colleagueRule = Ability::query()->whereMorphedTo('recipient', $colleague->user)->firstOrFail();
    $ownRule = Ability::query()->whereMorphedTo('recipient', $user)->firstOrFail();

    expect($member->canGrant($colleagueRule))->toBeTrue()
        ->and($member->canGrant($ownRule))->toBeFalse()
        ->and($member->canGrant('articles.edit', $ownRule))->toBeFalse();
});

it('checks a rule on an external record', function () {
    $team = team();
    $user = member($team, ['tickets.close']);
    $member = $team->member($user);
    $colleague = $team->member(member($team));

    $colleague->forbid('tickets.close')->on('ticket', 5);
    $member->forbid('tickets.close')->on('ticket', 6);

    expect($member->canGrant(Ability::query()->whereMorphedTo('recipient', $colleague->user)->firstOrFail()))->toBeTrue()
        ->and($member->canGrant(Ability::query()->whereMorphedTo('recipient', $user)->firstOrFail()))->toBeFalse();
});

it('lets the owner grant anything', function () {
    $team = team();
    $owner = $team->member($team->owner);

    expect($owner->canGrant('anything.at.all', role($team, 'admin', ['members.*'])))->toBeTrue();
});

it('accepts enum codes', function () {
    $team = team();
    $member = $team->member(member($team, ['articles.edit']));

    expect($member->canGrant(GrantCode::Edit))->toBeTrue();
});
