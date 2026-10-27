<?php

use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Models\Membership;

it('does not report a cancelled membership creation as successful', function () {
    $team = team();
    $role = role($team, 'reader');
    $user = user();
    Event::fake(TeamMemberAdded::class);
    Membership::saving(fn () => false);

    expect(fn () => $team->add($user)->as($role))->toThrow(RuntimeException::class, 'The membership could not be created.')
        ->and($user->in($team))->toBeFalse();

    Event::assertNotDispatched(TeamMemberAdded::class);
});

it('does not report a cancelled role assignment as successful', function () {
    $team = team();
    $user = member($team);
    $previous = $team->member($user)->role();
    $role = role($team, 'editor');
    Event::fake(TeamMemberUpdated::class);
    Membership::saving(fn () => false);

    expect(fn () => $team->member($user)->assign($role))->toThrow(RuntimeException::class, 'The membership could not be updated.')
        ->and($team->member($user)->is($previous))->toBeTrue();

    Event::assertNotDispatched(TeamMemberUpdated::class);
});

it('preserves membership, groups and rules when member deletion is cancelled', function () {
    $team = team();
    $user = member($team);
    $group = $team->groups()->create(['code' => 'editors'])->add($user);
    $team->member($user)->allow('articles.edit')->on(article($team));
    Event::fake(TeamMemberRemoved::class);
    Membership::deleting(fn () => false);

    expect(fn () => $team->remove($user))->toThrow(RuntimeException::class, 'The membership could not be removed.')
        ->and($user->in($team))->toBeTrue()
        ->and($group->members()->whereKey($user)->exists())->toBeTrue()
        ->and($user->abilities()->count())->toBe(1);

    Event::assertNotDispatched(TeamMemberRemoved::class);
});

it('rolls back member removal with its groups and rules', function () {
    $team = team();
    $user = member($team);
    $group = $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    $team->member($user)->allow('articles.edit')->on(article($team));
    Event::fake(TeamMemberRemoved::class);
    Membership::deleted(fn () => throw new RuntimeException('Deletion failed.'));

    expect(fn () => $team->remove($user))->toThrow(RuntimeException::class, 'Deletion failed.')
        ->and($user->in($team))->toBeTrue()
        ->and($group->members()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->abilities()->count())->toBe(1)
        ->and($team->member($user)->can('articles.edit'))->toBeTrue();

    Event::assertNotDispatched(TeamMemberRemoved::class);
});

it('rejects a role from another team', function () {
    $team = team();
    $user = user();

    expect(fn () => $team->add($user)->as(role(team(), 'editor')))
        ->toThrow(RuntimeException::class, 'role does not belong')
        ->and($user->in($team))->toBeFalse();
});

it('keeps the role when reassigning to a role from another team', function () {
    $team = team();
    $user = member($team);
    $role = $team->member($user)->role();

    expect(fn () => $team->member($user)->assign(role(team(), 'editor')))->toThrow(RuntimeException::class)
        ->and($team->member($user)->role()->is($role))->toBeTrue();
});

it('does not write the owner into the pivot', function () {
    $team = team();
    $role = role($team, 'editor');

    expect(fn () => $team->members()->attach($team->owner, ['role_id' => $role->id]))
        ->toThrow(RuntimeException::class, 'owner cannot be a member')
        ->and($team->members()->count())->toBe(0);
});

it('validates a moved membership against the new team', function () {
    $team = team();
    $user = member($team);
    $other = team();
    $membership = $team->members()->whereKey($user->id)->firstOrFail()->membership;
    $membership->setAttribute(config('teams.foreign_keys.team_id'), $other->id);

    expect(fn () => $membership->save())->toThrow(RuntimeException::class, 'role does not belong')
        ->and($user->in($team))->toBeTrue()
        ->and($user->in($other))->toBeFalse();
});

it('validates against the current owner when the team instance is stale', function () {
    $team = team();
    $role = role($team, 'editor');
    $user = user();
    $team->fresh()->update(['user_id' => $user->id]);

    expect(fn () => $team->members()->attach($user, ['role_id' => $role->id]))
        ->toThrow(RuntimeException::class, 'owner cannot be a member')
        ->and($team->members()->count())->toBe(0);
});

it('exposes the team, user and role of a membership', function () {
    $team = team();
    $user = member($team);
    $membership = $user->memberships()->firstOrFail()->membership;

    expect($membership)->toBeInstanceOf(Membership::class)
        ->and($membership->team->is($team))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($membership->role->is($team->member($user)->role()))->toBeTrue();
});
