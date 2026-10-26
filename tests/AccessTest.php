<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamCreating;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberAdding;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Events\TeamUpdating;
use Jurager\Teams\Mail\Invitation as InvitationMail;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Membership;
use Jurager\Teams\Models\Owner;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Rules\Role as RoleRule;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Support\Services\TeamsService;
use Jurager\Teams\Tests\Fixtures\Article;
use RuntimeException;

class AccessTest extends TestCase
{
    public function test_group_membership_filters_collections_and_clears_loaded_relations(): void
    {
        $team = $this->team();
        $owner = $team->owner;
        $member = $this->member($team);
        $outsider = $this->user();
        $group = $team->addGroup('editors');
        $group->load('users');
        $member->load('groups');

        $this->assertTrue($group->attachUser(new Collection([$owner, $member, $outsider])));
        $this->assertEqualsCanonicalizing([$owner->id, $member->id], $group->users->modelKeys());
        $this->assertTrue($member->groups->contains($group));

        $team->users()->detach($member->id);
        $this->assertTrue($group->detachUser(new Collection([$owner, $member])));
        $this->assertEmpty($group->users);
        $this->assertEmpty($member->groups);
        $this->assertFalse($group->detachUser($member));
    }

    public function test_removing_member_revokes_only_that_teams_group_and_user_rules(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $other = $this->team();
        $user = $this->member($team);
        $role = $other->addRole('other', []);
        $other->addUser($user, $role->id);
        $group = $team->addGroup('editors', ['articles.edit']);
        $otherGroup = $other->addGroup('editors', ['articles.edit']);
        $group->attachUser($user);
        $otherGroup->attachUser($user);
        $article = Article::create(['team_id' => $team->id]);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $team->load('users');
        $team->deleteUser($user);
        $this->assertFalse($team->hasUser($user));
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertFalse($group->users()->whereKey($user->id)->exists());
        $this->assertTrue($otherGroup->users()->whereKey($user->id)->exists());
        $this->assertCount(0, $user->teamAbilities($team, $article));
    }

    public function test_occupied_role_cannot_be_deleted_or_lose_its_rules(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $role = $user->teamRole($team);
        $article = Article::create(['team_id' => $team->id]);
        $user->forbidTeamAbility($team, 'articles.edit', $article, targetEntity: $role);

        try {
            $role->delete();
            $this->fail('Expected an occupied role to be rejected.');
        } catch (RuntimeException) {
            $this->assertNotNull($role->fresh());
            $this->assertCount(1, $role->permissions()->get());
            $this->assertCount(1, $role->abilities()->get());
            $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
        }
    }

    public function test_role_cleanup_rolls_back_when_a_deleting_listener_fails(): void
    {
        $team = $this->team();
        $role = $team->addRole('unused', ['articles.edit']);
        Event::listen('eloquent.deleting: '.Role::class, fn () => throw new RuntimeException('Listener failed.'));

        try {
            $team->deleteRole($role->id);
            $this->fail('Expected a listener exception.');
        } catch (RuntimeException) {
            $this->assertNotNull($role->fresh());
            $this->assertCount(1, $role->permissions()->get());
        }
    }

    public function test_deleting_an_ability_revokes_access_without_creating_records(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $article = Article::create(['team_id' => $team->id]);
        $user->deleteTeamAbility($team, 'missing.permission', $article);
        $this->assertSame(0, $team->permissions()->count());
        $this->assertSame(0, $team->abilities()->count());
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $user->deleteTeamAbility($team, 'articles.edit', $article);
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertSame(0, $user->abilities()->count());
    }

    public function test_ability_forms_share_morph_aliases_and_update_existing_rules(): void
    {
        Relation::morphMap(['article' => Article::class]);

        try {
            $team = $this->team();
            $user = $this->member($team);
            $article = Article::create(['team_id' => $team->id]);
            $user->allowTeamAbility($team, 'articles.edit', Article::class, $article->id);
            $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
            $user->forbidTeamAbility($team, 'articles.edit', $article);
            $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', Article::class, $article->id));
            $this->assertSame(1, $team->abilities()->count());
            $this->assertSame(1, $user->abilities()->count());
            $this->assertCount(1, $user->teamAbilities($team, $article, true));
            $user->deleteTeamAbility($team, 'articles.edit', Article::class, $article->id);
            $this->assertSame(0, $user->abilities()->count());
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_global_groups_grant_abilities_without_team_membership(): void
    {
        $team = $this->team();
        $user = $this->user();
        $group = Group::create(['code' => 'support', 'name' => 'Support', 'team_id' => null]);
        $group->permissions()->sync($team->getPermissionIds(['articles.*']));
        $this->assertTrue($group->attachUser($user));
        $this->assertFalse($group->attachUser($user));
        $article = Article::create(['team_id' => $team->id]);
        $this->assertFalse($user->belongsToTeam($team));
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertTrue($group->detachUser($user));
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
    }

    public function test_group_rules_and_user_rules_keep_their_precedence(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.*']);
        $group = $team->addGroup('editors');
        $group->attachUser($user);
        $article = Article::create(['team_id' => $team->id]);
        $user->forbidTeamAbility($team, 'articles.edit', $article, targetEntity: $group);
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $user->forbidTeamAbility($team, 'articles.edit', $article);
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
    }

    public function test_owner_non_member_and_role_user_relationships(): void
    {
        $team = $this->team();
        $owner = $team->owner->fresh();
        $this->assertInstanceOf(Owner::class, $team->userRole($owner));
        $this->assertSame('', $team->userRole($owner)->jsonSerialize()['description']);
        $this->assertSame('owner', $team->userRole($owner)->jsonSerialize()['code']);
        $this->assertNull($team->userRole($this->user()));
        $member = $this->member($team);
        $this->assertTrue($member->teamRole($team)->users->first()->is($member));
        $this->assertInstanceOf(Membership::class, $member->teamRole($team)->users->first()->pivot);
    }

    public function test_direct_membership_writes_clear_cached_decisions(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $role = $team->addRole('editor', ['articles.edit']);
        $user = $this->user();
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));

        $team->users()->attach($user, ['role_id' => $role->id]);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));

        $team->users()->detach($user);
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
    }

    public function test_all_users_skips_a_deleted_owner(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $team->owner->delete();

        $this->assertSame([$member->id], $team->fresh()->allUsers()->modelKeys());
    }

    public function test_role_rule_accepts_only_role_codes_of_the_team(): void
    {
        $team = $this->team();
        $team->addRole('editor', []);
        $this->team()->addRole('admin', []);
        $rules = ['role' => [new RoleRule($team)]];

        $this->assertTrue(Validator::make(['role' => 'editor'], $rules)->passes());
        $this->assertTrue(Validator::make(['role' => 'admin'], $rules)->fails());
        $this->assertTrue(Validator::make(['role' => ['editor']], $rules)->fails());
    }

    public function test_permission_codes_are_deduplicated_and_existing_codes_are_reused(): void
    {
        $team = $this->team();
        $ids = $team->getPermissionIds(['articles.edit', 'articles.edit']);
        $this->assertCount(1, $ids);
        $this->assertSame($ids, $team->getPermissionIds(['articles.edit']));
        $this->assertSame(1, $team->permissions()->count());
    }

    public function test_decision_cache_is_invalidated_by_mutations_and_new_lifecycles(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $team->updateRole($user->teamRole($team)->id, []);
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $role = $user->teamRole($team);
        $role->permissions()->sync($team->getPermissionIds(['articles.edit']));
        $this->startNewLifecycle();
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertSame(app('teams'), app(TeamsService::class));
        $this->assertSame(app('teams'), Teams::getFacadeRoot());
    }

    public function test_decisions_do_not_outlive_a_queued_job_or_octane_request(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $service = app(TeamsService::class);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));

        // Another process revokes the permission without firing local model events.
        DB::table(config('teams.tables.entity_permission'))->delete();
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));

        $this->startNewLifecycle();
        $this->assertNotSame($service, app(TeamsService::class));
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
    }

    /**
     * Reset scoped services the way queue workers and Octane do between operations.
     */
    private function startNewLifecycle(): void
    {
        $this->app->forgetScopedInstances();
        Facade::clearResolvedInstances();
    }

    public function test_team_loading_and_role_resolution_each_need_one_query(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        DB::enableQueryLog();
        Team::findOrFail($team->id);
        $this->assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertInstanceOf(Role::class, $user->teamRole($team));
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_invitation_mail_renders_and_a_pending_invitation_can_be_resent(): void
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('editor', []);
        $team->inviteUser('invited@example.com', 'editor');
        $team->inviteUser('invited@example.com', 'editor');
        $this->assertSame(1, $team->invitations()->count());
        Mail::assertSent(InvitationMail::class, 2);
        $invitation = $team->invitations()->first();
        $html = (new InvitationMail($invitation))->render();
        $this->assertStringContainsString('/invitation/'.$invitation->id.'/accept', $html);
        $this->assertStringContainsString('signature=', $html);
    }

    public function test_accepting_invitation_is_atomic_when_a_pre_change_listener_fails(): void
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('editor', []);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $invitation = $team->invitations()->first();
        Event::listen(TeamMemberAdding::class, fn () => throw new RuntimeException('Listener failed.'));

        try {
            $team->inviteAccept($invitation->id);
            $this->fail('Expected a listener exception.');
        } catch (RuntimeException) {
            $this->assertFalse($team->hasUser($user));
            $this->assertNotNull($invitation->fresh());
        }
    }

    public function test_after_commit_listener_failure_does_not_undo_invitation_acceptance(): void
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('editor', []);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $invitation = $team->invitations()->firstOrFail();
        Event::listen(TeamMemberAdded::class, fn () => throw new RuntimeException('Listener failed.'));

        try {
            $team->inviteAccept($invitation->id);
            $this->fail('Expected a listener exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Listener failed.', $exception->getMessage());
            $this->assertTrue($team->hasUser($user));
            $this->assertSame(0, $team->invitations()->count());
            $this->assertSame(0, $team->getConnection()->transactionLevel());
        }
    }

    public function test_invitation_route_accepts_using_the_package_controller(): void
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('editor', ['articles.edit']);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $invitation = $team->invitations()->firstOrFail();
        $parameters = ['invitation_id' => $invitation->getKey()];
        $url = URL::signedRoute('teams.invitations.accept', $parameters);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->user())->get($url)->assertForbidden();
        $this->actingAs($user)->get(route('teams.invitations.accept', $parameters))->assertForbidden();
        $this->assertFalse($team->hasUser($user));

        $this->actingAs($user)->get($url)->assertRedirect('/');
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertSame(0, $team->invitations()->count());
        $this->get($url)->assertNotFound();
    }

    public function test_invitation_link_expires_after_the_configured_time(): void
    {
        Mail::fake();
        config(['teams.invitations.expire' => 60]);
        $team = $this->team();
        $team->addRole('editor', []);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $html = (new InvitationMail($team->invitations()->firstOrFail()))->render();
        preg_match('/href="([^"]*\/accept[^"]*)"/', $html, $matches);
        $url = html_entity_decode($matches[1]);
        $this->assertStringContainsString('expires=', $url);

        $this->travel(61)->minutes();

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($team->hasUser($user));
    }

    public function test_team_lifecycle_events_are_dispatched(): void
    {
        Event::fake([TeamCreating::class, TeamCreated::class, TeamUpdating::class, TeamUpdated::class, TeamDeleted::class]);
        $team = $this->team();
        $team->update(['name' => 'Renamed']);
        $team->delete();
        Event::assertDispatched(TeamCreating::class, fn ($event) => $event->team->is($team));
        Event::assertDispatched(TeamCreated::class);
        Event::assertDispatched(TeamUpdating::class);
        Event::assertDispatched(TeamUpdated::class);
        Event::assertDispatched(TeamDeleted::class);
    }
}
