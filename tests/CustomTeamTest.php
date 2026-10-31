<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamTransferred;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Mail\Invitation;
use Jurager\Teams\Tests\Fixtures\User;
use Jurager\Teams\Tests\Fixtures\Workspace;
use Jurager\Teams\Tests\TestCase;

beforeAll(fn () => TestCase::configure([
    'teams.models.team' => Workspace::class,
    'teams.tables.teams' => 'workspaces',
    'teams.foreign_keys.team_id' => 'workspace_id',
]));

it('gives an independent team model the whole api with one trait', function () {
    Event::fake([TeamCreated::class, TeamUpdated::class, TeamDeleted::class]);
    $owner = user();
    $team = $owner->teams()->create(['name' => 'Acme']);
    $user = user();
    $role = role($team, 'editor', ['articles.view']);
    $group = $team->groups()->create(['code' => 'publishers'])->grant('articles.publish');
    $team->add($user)->as('editor');
    $group->add($user);
    $article = article($team);
    $team->member($user)->allow('articles.edit')->on($article);

    expect($team)->toBeInstanceOf(Workspace::class)
        ->and($team->owner->is($owner))->toBeTrue()
        ->and($user->in($team))->toBeTrue()
        ->and($team->role('editor')->is($role))->toBeTrue()
        ->and($team->group('publishers')->is($group))->toBeTrue()
        ->and($user->can(['articles.view', 'articles.publish'], $team))->toBeTrue()
        ->and($user->memberships()->firstOrFail()->is($team))->toBeTrue()
        ->and($user->can('articles.edit', $article))->toBeTrue();

    expect($user->accessible()->first())->toBeInstanceOf(Workspace::class)
        ->and($team->rules($article)->first()->recipient->is($user))->toBeTrue();

    $team->update(['name' => 'Renamed']);
    $team->remove($user);

    expect($user->in($team))->toBeFalse()
        ->and($user->groups()->get())->toBeEmpty()
        ->and($user->abilities()->get())->toBeEmpty();

    $group->delete();
    $role->delete();
    $team->purge();

    $this->assertModelMissing($team);
    Event::assertDispatchedTimes(TeamCreated::class, 1);
    Event::assertDispatchedTimes(TeamUpdated::class, 1);
    Event::assertDispatchedTimes(TeamDeleted::class, 1);
    Event::assertDispatched(TeamCreated::class, fn ($event) => $event->team === $team);
});

it('sends and accepts invitations for an independent team model', function () {
    Mail::fake();
    $team = team();
    role($team, 'editor', ['articles.edit']);
    $user = user();
    $invitation = $team->invite($user->email)->as('editor');

    expect($invitation->team)->toBeInstanceOf(Workspace::class);

    $invitation->accept();

    expect($user->can('articles.edit', $team))->toBeTrue();
    $this->assertModelMissing($invitation);
    Mail::assertSent(Invitation::class, 1);
});

it('supports permission queries, member batches and expiring rules with one team trait', function () {
    $team = team();
    role($team, 'reader', ['articles.view']);
    $users = [user(), user()];
    $members = $team->add($users)->as('reader');
    $article = article($team);
    $members->first()->allow('articles.edit')->until(now()->addHour())->on($article);

    expect(User::permission('articles.view', $team)->count())->toBe(3)
        ->and($members->first()->can('articles.edit', $article))->toBeTrue();

    $team->remove($users);
    expect($team->members()->count())->toBe(0);
});

it('transfers an independent team and removes the former owners team assignments', function () {
    $team = team();
    $previous = $team->owner;
    $next = member($team);
    $article = article($team);
    $group = $team->groups()->create(['code' => 'editors'])->add($previous);
    $team->member($previous)->allow('articles.edit')->on($article);
    Event::fake(TeamTransferred::class);

    $team->transfer($next);

    expect($team->owner->is($next))->toBeTrue()
        ->and($previous->in($team))->toBeFalse()
        ->and($group->members()->whereKey($previous)->exists())->toBeFalse()
        ->and($previous->abilities()->count())->toBe(0)
        ->and($team->member($next)->inspect('articles.edit', $article)->code())->toBe('team_owner');
    Event::assertDispatched(TeamTransferred::class, fn ($event) => $event->team === $team
        && $event->owner->is($next) && $event->previous->is($previous));
});
