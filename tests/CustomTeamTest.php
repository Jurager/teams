<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Mail\Invitation;
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
