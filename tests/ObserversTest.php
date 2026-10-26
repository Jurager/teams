<?php

namespace Jurager\Teams\Tests;

use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamCreating;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Events\TeamUpdating;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Permission;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\Article;
use RuntimeException;

class ObserversTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('teams.request.cache_decisions', true);
        $app['config']->set('teams.models.team', ObservedTeam::class);
        $app['config']->set('teams.models.role', ObservedRole::class);
        $app['config']->set('teams.models.group', ObservedGroup::class);
        $app['config']->set('teams.models.permission', ObservedPermission::class);
    }

    protected function team(): Team
    {
        return ObservedTeam::create(['user_id' => $this->user()->getKey(), 'name' => 'Example']);
    }

    public function test_configured_team_dispatches_each_lifecycle_event_once(): void
    {
        Event::fake([TeamCreating::class, TeamCreated::class, TeamUpdating::class, TeamUpdated::class, TeamDeleted::class]);
        $team = $this->team();
        $team->update(['name' => 'Renamed']);
        $team->delete();

        foreach ([TeamCreating::class, TeamCreated::class, TeamUpdating::class, TeamUpdated::class, TeamDeleted::class] as $event) {
            Event::assertDispatchedTimes($event, 1);
        }

        Event::assertDispatched(TeamCreating::class, fn ($event) => $event->team->is($team));
    }

    public function test_configured_role_preserves_permissions_when_deletion_is_rejected(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $role = $user->teamRole($team);

        try {
            $role->delete();
            $this->fail('Expected an occupied role to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Reassign or remove team members', $exception->getMessage());
            $this->assertNotNull($role->fresh());
            $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        }
    }

    public function test_configured_group_deletion_cleans_assignments_and_invalidates_decisions(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $group = $team->addGroup('editors', ['articles.edit']);
        $group->attachUser($user);
        $article = Article::create(['team_id' => $team->id]);
        $user->allowTeamAbility($team, 'articles.edit', $article, targetEntity: $group);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $team->deleteGroup($group->id);

        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));

        foreach (['entity_permission', 'entity_ability'] as $table) {
            $this->assertDatabaseMissing(config("teams.tables.{$table}"), [
                'entity_type' => $group->getMorphClass(),
                'entity_id' => $group->getKey(),
            ]);
        }
    }

    public function test_configured_permission_save_and_delete_invalidate_decisions(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $permission = $team->permissions()->firstOrFail();
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $permission->update(['code' => 'articles.view']);
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertTrue($user->hasTeamPermission($team, 'articles.view'));
        $permission->delete();
        $this->assertFalse($user->hasTeamPermission($team, 'articles.view'));
    }
}

class ObservedTeam extends Team
{
    protected static function booted(): void {}
}

class ObservedRole extends Role
{
    protected static function booted(): void {}
}

class ObservedGroup extends Group
{
    protected static function booted(): void {}
}

class ObservedPermission extends Permission
{
    protected static function booted(): void {}
}
