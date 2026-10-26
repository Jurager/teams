<?php

use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Invitation;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\CustomRole;

it('creates a team with an owner and members', function () {
    $team = Team::factory()->members(2, 'editor')->create();

    expect($team->owner)->not->toBeNull()
        ->and($team->members)->toHaveCount(2)
        ->and($team->member($team->members->first())->is('editor'))->toBeTrue();
});

it('creates roles and groups with permissions', function () {
    $team = Team::factory()->create();

    $role = Role::factory()->for($team)->grant('articles.view', 'articles.edit')->create();
    $group = Group::factory()->global()->grant('tickets.*')->create();

    expect($role->team->is($team))->toBeTrue()
        ->and($role->permissions->pluck('code')->all())->toEqualCanonicalizing(['articles.view', 'articles.edit'])
        ->and($group->team_id)->toBeNull()
        ->and($group->permissions->pluck('code')->all())->toBe(['tickets.*']);
});

it('creates an invitation with a role of its team', function () {
    $invitation = Invitation::factory()->create();

    expect($invitation->role->team->is($invitation->team))->toBeTrue()
        ->and($invitation->email)->toContain('@');
});

it('creates the configured model', function () {
    config(['teams.models.role' => CustomRole::class]);

    expect(Role::factory()->create())->toBeInstanceOf(CustomRole::class);
});
