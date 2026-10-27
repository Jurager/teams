<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamTransferred;
use Jurager\Teams\Events\TeamTransferring;
use Jurager\Teams\Models\Membership;
use Jurager\Teams\Models\Team;

it('transfers the team to a member and lets the previous owner leave', function () {
    $team = team();
    $previous = $team->owner;
    $user = member($team);
    $group = $team->groups()->create(['code' => 'editors'])->add($previous);
    $team->member($previous)->allow('articles.edit')->on(article($team));

    $team->transfer($user);

    expect($user->owns($team))->toBeTrue()
        ->and($team->owner->is($user))->toBeTrue()
        ->and($team->members)->toBeEmpty()
        ->and($previous->in($team))->toBeFalse()
        ->and($group->members()->whereKey($previous->id)->exists())->toBeFalse()
        ->and($previous->abilities()->count())->toBe(0);
});

it('keeps the previous owner as a member with the given role', function () {
    $team = team();
    $previous = $team->owner;
    role($team, 'admin');

    $team->transfer($user = user(), 'admin');

    expect($user->owns($team))->toBeTrue()
        ->and($team->member($previous)->is('admin'))->toBeTrue()
        ->and($team->members->modelKeys())->toBe([$previous->id]);
});

it('refreshes loaded members and ownership relationships when retaining the previous owner', function () {
    $team = team();
    $previous = $team->owner->load('teams', 'memberships');
    $next = member($team)->load('teams', 'memberships');
    $team->load('members');
    role($team, 'reader');

    $team->transfer($next, 'reader');

    expect($team->members->modelKeys())->toBe([$previous->id])
        ->and($previous->teams)->toBeEmpty()
        ->and($previous->memberships->first()->is($team))->toBeTrue()
        ->and($previous->memberships->first()->membership->role->code)->toBe('reader')
        ->and($next->teams->first()->is($team))->toBeTrue()
        ->and($next->memberships)->toBeEmpty();
});

it('does not transfer to the current owner or with an unknown role', function () {
    $team = team();
    $owner = $team->owner;
    $user = member($team);

    expect(fn () => $team->transfer($owner))->toThrow(RuntimeException::class, 'already owns')
        ->and(fn () => $team->transfer($user, 'missing'))->toThrow(ModelNotFoundException::class)
        ->and($team->fresh()->owner->is($owner))->toBeTrue()
        ->and($user->in($team))->toBeTrue();
});

it('dispatches the transfer events with both owners', function () {
    Event::fake([TeamTransferring::class, TeamTransferred::class]);
    $team = team();
    $previous = $team->owner;

    $team->transfer($user = user());

    foreach ([TeamTransferring::class, TeamTransferred::class] as $event) {
        Event::assertDispatched($event, fn ($event) => $event->team->is($team)
            && $event->owner->is($user)
            && $event->previous->is($previous));
    }
});

it('rolls back the transfer when a listener fails', function () {
    $team = team();
    $owner = $team->owner;
    $user = member($team);
    Event::listen(TeamTransferring::class, fn () => throw new RuntimeException('Listener failed.'));

    expect(fn () => $team->transfer($user))->toThrow(RuntimeException::class, 'Listener failed.')
        ->and($team->fresh()->owner->is($owner))->toBeTrue()
        ->and($user->in($team))->toBeTrue();
});

it('transfers a stale team back to its original owner using current database state', function () {
    $team = team();
    $original = $team->owner;
    role($team, 'reader');
    $previous = user();
    $team->fresh()->transfer($previous, 'reader');
    Event::fake(TeamTransferred::class);

    $team->transfer($original, 'reader');

    expect($team->fresh()->owner->is($original))->toBeTrue()
        ->and($team->owner->is($original))->toBeTrue()
        ->and($team->members()->whereKey($original)->exists())->toBeFalse()
        ->and($team->member($previous)->is('reader'))->toBeTrue();

    Event::assertDispatched(TeamTransferred::class, fn ($event) => $event->team === $team
        && $event->owner->is($original) && $event->previous->is($previous));
});

it('rolls back a transfer when saving the owner is cancelled', function () {
    $team = team();
    $owner = $team->owner;
    $next = member($team);
    Event::fake(TeamTransferred::class);
    Team::saving(fn () => false);

    expect(fn () => $team->transfer($next))->toThrow(RuntimeException::class, 'The team ownership could not be transferred.')
        ->and($team->fresh()->owner->is($owner))->toBeTrue()
        ->and($next->memberships()->whereKey($team)->exists())->toBeTrue();

    Event::assertNotDispatched(TeamTransferred::class);
});

it('rolls back a transfer when removing the next owners membership is cancelled', function () {
    $team = team();
    $owner = $team->owner;
    $next = member($team);
    Event::fake(TeamTransferred::class);
    Membership::deleting(fn () => false);

    expect(fn () => $team->transfer($next))->toThrow(RuntimeException::class, 'The membership could not be removed.')
        ->and($team->fresh()->owner->is($owner))->toBeTrue()
        ->and($next->in($team))->toBeTrue();

    Event::assertNotDispatched(TeamTransferred::class);
});

it('rolls back a transfer when retaining the previous owner is cancelled', function () {
    $team = team();
    $owner = $team->owner;
    $next = member($team);
    $role = role($team, 'reader');
    Event::fake(TeamTransferred::class);
    Membership::saving(fn () => false);

    expect(fn () => $team->transfer($next, $role))->toThrow(RuntimeException::class, 'The membership could not be created.')
        ->and($team->fresh()->owner->is($owner))->toBeTrue()
        ->and($next->memberships()->whereKey($team)->exists())->toBeTrue()
        ->and($team->members()->whereKey($owner)->exists())->toBeFalse();

    Event::assertNotDispatched(TeamTransferred::class);
});
