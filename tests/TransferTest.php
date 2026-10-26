<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamTransferred;
use Jurager\Teams\Events\TeamTransferring;

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
