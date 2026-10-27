<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamCreating;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Events\TeamUpdating;
use Jurager\Teams\Models\Membership;
use Jurager\Teams\Models\Owner;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Models\Team;

it('lets a user create and own teams', function () {
    $owner = user();
    $team = $owner->teams()->create(['name' => 'Acme']);

    expect($owner->owns($team))->toBeTrue()
        ->and($owner->in($team))->toBeTrue()
        ->and($team->owner->is($owner))->toBeTrue()
        ->and($owner->teams->first()->is($team))->toBeTrue()
        ->and($owner->memberships)->toBeEmpty()
        ->and($team->members)->toBeEmpty();
});

it('adds, reassigns and removes members', function () {
    $team = team();
    role($team, 'reader');
    role($team, 'editor');
    $jane = user();

    $member = $team->add($jane)->as('reader');

    expect($member->is('reader'))->toBeTrue()
        ->and($jane->in($team))->toBeTrue()
        ->and($jane->owns($team))->toBeFalse()
        ->and($team->members->first()->is($jane))->toBeTrue()
        ->and($jane->memberships->first()->membership)->toBeInstanceOf(Membership::class);

    $team->member($jane)->assign($team->role('editor'));

    expect($team->member($jane)->is('editor', 'admin'))->toBeTrue()
        ->and($team->member($jane)->is('reader'))->toBeFalse()
        ->and($team->member($jane)->role()->members->first()->is($jane))->toBeTrue();

    $team->remove($jane);

    expect($jane->in($team))->toBeFalse()
        ->and($team->member($jane)->role())->toBeNull()
        ->and($team->members)->toBeEmpty();
});

it('rejects membership changes that break the rules', function (Closure $change) {
    $team = team();
    role($team, 'reader');
    $team->add(user())->as('reader');

    expect(fn () => $change($team))->toThrow(RuntimeException::class)
        ->and($team->members()->count())->toBe(1);
})->with([
    'adding a member twice' => fn ($team) => $team->add($team->members->first())->as('reader'),
    'adding the owner' => fn ($team) => $team->add($team->owner)->as('reader'),
    'reassigning the owner' => fn ($team) => $team->member($team->owner)->assign('reader'),
    'reassigning a stranger' => fn ($team) => $team->member(user())->assign('reader'),
    'removing the owner' => fn ($team) => $team->remove($team->owner),
    'removing a stranger' => fn ($team) => $team->remove(user()),
]);

it('throws for unknown role and group codes', function () {
    $team = team();

    expect(fn () => $team->role('missing'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $team->group('missing'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $team->add(user())->as('missing'))->toThrow(ModelNotFoundException::class)
        ->and($team->members()->count())->toBe(0);
});

it('gives the owner every role and strangers none', function () {
    $team = team();
    $owner = $team->member($team->owner);
    $stranger = $team->member(user());

    expect($owner->role())->toBeInstanceOf(Owner::class)
        ->and($owner->role()->jsonSerialize())->toBe(['id' => null, 'code' => 'owner', 'name' => 'Owner'])
        ->and($owner->is('anything'))->toBeTrue()
        ->and($owner->permissions())->toBe(['*'])
        ->and($stranger->role())->toBeNull()
        ->and($stranger->is('anything'))->toBeFalse()
        ->and($stranger->permissions())->toBe([]);
});

it('revokes only the groups and rules of the team a member leaves', function () {
    $team = team();
    $other = team();
    $user = member($team);
    role($other, 'other');
    $other->add($user)->as('other');
    $group = $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    $otherGroup = $other->groups()->create(['code' => 'editors'])->add($user);
    $team->member($user)->allow('articles.edit')->on(article($team));

    $team->remove($user);

    expect($team->member($user)->can('articles.edit'))->toBeFalse()
        ->and($group->members()->whereKey($user->id)->exists())->toBeFalse()
        ->and($otherGroup->members()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->abilities()->count())->toBe(0)
        ->and($user->in($other))->toBeTrue();
});

it('adds only team users to a group and clears loaded relations', function () {
    $team = team();
    $member = member($team);
    $group = $team->groups()->create(['code' => 'editors'])->load('members');
    $member->load('groups');

    $group->add(new Collection([$team->owner, $member, user()]));

    expect($group->members->modelKeys())->toEqualCanonicalizing([$team->owner->id, $member->id])
        ->and($member->groups->contains($group))->toBeTrue();

    $group->remove(new Collection([$team->owner, $member]));

    expect($group->members)->toBeEmpty()
        ->and($member->groups)->toBeEmpty();
});

it('names roles and groups after their code', function () {
    $team = team();

    expect($team->roles()->create(['code' => 'content-editor'])->name)->toBe('Content Editor')
        ->and($team->groups()->create(['code' => 'writers', 'name' => 'Authors'])->name)->toBe('Authors');
});

it('purges a team with its members', function () {
    $team = team();
    member($team);

    $team->purge();

    $this->assertModelMissing($team);
    expect(Membership::count())->toBe(0);
});

it('preserves members when team deletion is cancelled', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    Event::fake(TeamDeleted::class);
    Team::deleting(fn () => false);

    expect(fn () => $team->purge())->toThrow(RuntimeException::class, 'The team could not be deleted.')
        ->and($team->fresh())->not->toBeNull()
        ->and($team->member($user)->can('articles.edit'))->toBeTrue();

    Event::assertNotDispatched(TeamDeleted::class);
});

it('loads a team and resolves a role with one query each', function () {
    $team = team();
    $user = member($team);
    DB::enableQueryLog();

    Team::findOrFail($team->id);
    expect(DB::getQueryLog())->toHaveCount(1);

    DB::flushQueryLog();
    expect($team->member($user)->role())->toBeInstanceOf(Role::class)
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('dispatches each team lifecycle event once', function () {
    $events = [TeamCreating::class, TeamCreated::class, TeamUpdating::class, TeamUpdated::class, TeamDeleted::class];
    Event::fake($events);

    $team = team();
    $team->update(['name' => 'Renamed']);
    $team->delete();

    foreach ($events as $event) {
        Event::assertDispatchedTimes($event, 1);
    }

    Event::assertDispatched(TeamCreating::class, fn ($event) => $event->team->is($team));
});
