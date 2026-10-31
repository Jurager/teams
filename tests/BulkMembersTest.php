<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberAdding;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberRemoving;
use Jurager\Teams\Member;
use Jurager\Teams\Models\Membership;

it('adds and removes distinct users from iterable inputs', function (Closure $input) {
    $team = team()->load('members');
    role($team, 'reader');
    $first = user()->load('memberships');
    $second = user()->load('memberships');
    Event::fake([TeamMemberAdding::class, TeamMemberAdded::class, TeamMemberRemoving::class, TeamMemberRemoved::class]);

    $members = $team->add($input([$first, $second, $first]))->as('reader');

    expect($members)->toBeInstanceOf(Collection::class)
        ->and($members)->toHaveCount(2)
        ->and($members->every(fn ($member) => $member instanceof Member && $member->is('reader')))->toBeTrue()
        ->and($team->members->modelKeys())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($first->memberships)->toHaveCount(1)
        ->and($second->memberships)->toHaveCount(1);

    $team->remove($input([$first, $second, $first]));

    expect($team->members)->toBeEmpty()
        ->and($first->memberships)->toBeEmpty()
        ->and($second->memberships)->toBeEmpty();

    foreach ([TeamMemberAdding::class, TeamMemberAdded::class, TeamMemberRemoving::class, TeamMemberRemoved::class] as $event) {
        Event::assertDispatchedTimes($event, 2);
    }
})->with([
    'array' => fn (array $users) => $users,
    'collection' => fn (array $users) => collect($users),
    'generator' => function (array $users) {
        yield from $users;
    },
]);

it('does not query or dispatch events for empty batches', function () {
    $team = team();
    Event::fake([TeamMemberAdded::class, TeamMemberRemoved::class]);
    DB::enableQueryLog();

    expect($team->add([])->as('missing'))->toBeEmpty();
    $team->remove(collect());

    expect(DB::getQueryLog())->toBeEmpty();
    Event::assertNotDispatched(TeamMemberAdded::class);
    Event::assertNotDispatched(TeamMemberRemoved::class);
});

it('validates every user before starting a batch addition', function (bool $owner) {
    $team = team();
    role($team, 'reader');
    $first = user();
    $invalid = $owner ? $team->owner : member($team);
    Event::fake([TeamMemberAdding::class, TeamMemberAdded::class]);

    expect(fn () => $team->add([$first, $invalid])->as('reader'))->toThrow(RuntimeException::class, 'already belongs')
        ->and($first->in($team))->toBeFalse();

    Event::assertNotDispatched(TeamMemberAdding::class);
    Event::assertNotDispatched(TeamMemberAdded::class);
})->with([false, true]);

it('rolls back all additions when a later membership is cancelled', function () {
    $team = team();
    role($team, 'reader');
    $first = user();
    $second = user();
    Event::fake(TeamMemberAdded::class);
    Membership::saving(fn (Membership $membership) => $membership->user_id === $second->id ? false : null);

    expect(fn () => $team->add([$first, $second])->as('reader'))->toThrow(RuntimeException::class, 'could not be created')
        ->and($team->members()->count())->toBe(0);

    Event::assertNotDispatched(TeamMemberAdded::class);
});

it('validates every user before starting a batch removal', function (bool $owner) {
    $team = team();
    $first = member($team);
    $invalid = $owner ? $team->owner : user();
    Event::fake([TeamMemberRemoving::class, TeamMemberRemoved::class]);

    expect(fn () => $team->remove([$first, $invalid]))->toThrow(RuntimeException::class)
        ->and($first->in($team))->toBeTrue();

    Event::assertNotDispatched(TeamMemberRemoving::class);
    Event::assertNotDispatched(TeamMemberRemoved::class);
})->with([false, true]);

it('rolls back memberships, groups and rules when a later removal is cancelled', function () {
    $team = team();
    $first = member($team);
    $second = member($team);
    $group = $team->groups()->create(['code' => 'editors'])->add($first)->add($second);
    $team->member($first)->allow('articles.edit')->on(article($team));
    Event::fake(TeamMemberRemoved::class);
    Membership::deleting(fn (Membership $membership) => $membership->user_id === $second->id ? false : null);

    expect(fn () => $team->remove([$first, $second]))->toThrow(RuntimeException::class, 'could not be removed')
        ->and($team->members()->count())->toBe(2)
        ->and($group->members()->count())->toBe(2)
        ->and($first->abilities()->count())->toBe(1);

    Event::assertNotDispatched(TeamMemberRemoved::class);
});

it('dispatches each completed batch change after the outermost commit', function () {
    $team = team();
    role($team, 'reader');
    $users = [user(), user()];
    $events = [];

    foreach ([TeamMemberAdded::class, TeamMemberRemoved::class] as $event) {
        Event::listen($event, function ($event) use (&$events) {
            $events[] = [$event::class, $event->user->id, DB::transactionLevel()];
        });
    }

    DB::transaction(function () use ($team, $users, &$events) {
        $team->add($users)->as('reader');
        expect($events)->toBeEmpty();
    });

    expect($events)->toBe([
        [TeamMemberAdded::class, $users[0]->id, 0],
        [TeamMemberAdded::class, $users[1]->id, 0],
    ]);
    $events = [];

    DB::transaction(function () use ($team, $users, &$events) {
        $team->remove($users);
        expect($events)->toBeEmpty();
    });

    expect($events)->toBe([
        [TeamMemberRemoved::class, $users[0]->id, 0],
        [TeamMemberRemoved::class, $users[1]->id, 0],
    ]);
});
