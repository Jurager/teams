<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

enum PermissionCode: string
{
    case View = 'articles.view';
    case Edit = 'articles.edit';
    case All = 'articles.*';
}

enum RoleCode: string
{
    case Reader = 'reader';
    case Editor = 'editor';
}

enum GroupCode: string
{
    case Reviewers = 'reviewers';
}

it('stores enum model codes as strings with their default names', function () {
    $team = team();
    $role = $team->roles()->create(['code' => RoleCode::Reader]);
    $group = $team->groups()->create(['code' => GroupCode::Reviewers]);
    $permission = $team->permissions()->create(['code' => PermissionCode::View]);

    expect($role->code)->toBe('reader')
        ->and($role->name)->toBe('Reader')
        ->and($group->code)->toBe('reviewers')
        ->and($group->name)->toBe('Reviewers')
        ->and($permission->code)->toBe('articles.view')
        ->and($permission->name)->toBe('Articles View');

    $role->update(['code' => RoleCode::Editor]);
    expect($role->code)->toBe('editor')->and($role->fresh()->code)->toBe('editor');
});

it('accepts enums and strings when granting, syncing and revoking permissions', function () {
    $role = role(team(), 'editor');
    $role->grant(PermissionCode::View, [PermissionCode::Edit, 'articles.publish' => 'Publish articles']);

    expect($role->permissions->pluck('code')->all())->toEqualCanonicalizing(['articles.view', 'articles.edit', 'articles.publish']);

    $role->revoke([PermissionCode::Edit], 'articles.publish');
    expect($role->permissions->pluck('code')->all())->toBe(['articles.view']);

    $role->sync(PermissionCode::All);
    expect($role->permissions->pluck('code')->all())->toBe(['articles.*']);
});

it('accepts role and group enums throughout the membership api', function () {
    Mail::fake();
    $team = team();
    role($team, 'reader');
    role($team, 'editor', ['articles.*']);
    $team->groups()->create(['code' => 'reviewers']);
    $user = user();
    $member = $team->add($user)->as(RoleCode::Reader);

    expect($member->is(RoleCode::Reader))->toBeTrue()
        ->and($team->role(RoleCode::Editor)->code)->toBe('editor')
        ->and($team->group(GroupCode::Reviewers)->code)->toBe('reviewers');

    $member->assign(RoleCode::Editor);
    expect($member->is(RoleCode::Editor, 'reader'))->toBeTrue()
        ->and($team->invite('new@example.com')->as(RoleCode::Reader)->role->code)->toBe('reader');

    $previous = $team->owner;
    $team->transfer($user, RoleCode::Reader);
    expect($team->member($previous)->is(RoleCode::Reader))->toBeTrue();
});

it('accepts enums in checks and record rules', function () {
    $team = team();
    $user = member($team);
    $member = $team->member($user);
    $role = $member->role();
    $article = article($team);
    $group = $team->groups()->create(['code' => 'reviewers'])->add($user);
    $role->grant(PermissionCode::All);

    expect($member->can(PermissionCode::Edit))->toBeTrue()
        ->and($member->can([PermissionCode::View, 'articles.edit']))->toBeTrue()
        ->and($member->canAny(['missing', PermissionCode::Edit]))->toBeTrue()
        ->and($member->inspect(PermissionCode::Edit)->code())->toBe('role_permission')
        ->and(Gate::forUser($user)->inspect(PermissionCode::Edit->value, $team)->allowed())->toBeTrue();

    $role->forbid(PermissionCode::Edit)->on($article);
    $group->allow(PermissionCode::Edit)->on($article);
    $member->forbid(PermissionCode::Edit)->on($article);
    expect($member->can(PermissionCode::Edit, $article))->toBeFalse();

    $member->forget(PermissionCode::Edit)->on($article);
    expect($member->can(PermissionCode::Edit, $article))->toBeTrue();

    $group->forget(PermissionCode::Edit)->on($article);
    expect($member->can(PermissionCode::Edit, $article))->toBeFalse();

    $role->forget(PermissionCode::Edit)->on($article);
    expect($member->can(PermissionCode::Edit, $article))->toBeTrue();

    $member->allow(PermissionCode::View)->on($article);
    $member->load([$article]);
    expect($member->can([PermissionCode::View, PermissionCode::Edit], $article))->toBeTrue();
});
