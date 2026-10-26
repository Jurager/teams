<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Mail\Invitation;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Traits\HasMembers;

class CustomTeamTest extends TestCase
{
    public function test_resolving_permission_ids_preserves_the_team_relationship_constraints(): void
    {
        $team = ScopedWorkspace::create(['user_id' => $this->user()->getKey(), 'name' => 'Scoped']);
        $ids = $team->getPermissionIds(['visible.read', 'hidden.edit']);

        $this->assertCount(1, $ids);
        $this->assertSame($team->permissions()->where('code', 'visible.read')->pluck('id')->all(), $ids);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('teams.models.team', Workspace::class);
        $app['config']->set('teams.tables.teams', 'workspaces');
        $app['config']->set('teams.foreign_keys.team_id', 'workspace_id');
        $app['config']->set('teams.request.cache_decisions', true);
    }

    public function test_one_trait_provides_the_complete_api_to_an_independent_team_model(): void
    {
        Event::fake([TeamCreated::class, TeamUpdated::class, TeamDeleted::class]);
        $owner = $this->user();
        $team = $owner->ownedTeams()->create(['name' => 'Acme']);
        $user = $this->user();
        $role = $team->addRole('editor', ['articles.view'], description: 'Editor');
        $group = $team->addGroup('publishers', ['articles.publish']);
        $team->addUser($user, 'editor');
        $group->attachUser($user);

        $this->assertInstanceOf(Workspace::class, $team);
        $this->assertTrue($team->owner->is($owner));
        $this->assertTrue($team->hasUser($user));
        $this->assertTrue($team->hasRole('editor'));
        $this->assertTrue($team->hasGroup('publishers'));
        $this->assertTrue($user->hasTeamPermission($team, ['articles.view', 'articles.publish'], true));
        $this->assertTrue($user->teams()->firstOrFail()->is($team));
        $this->assertNull($team->updateRole('editor', description: null)->description);
        $this->assertSame('Publishers', $team->updateGroup('publishers', name: 'Publishers')->name);

        $article = Article::create(['workspace_id' => $team->getKey()]);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));

        $team->update(['name' => 'Renamed']);
        $team->deleteUser($user);
        $this->assertFalse($team->hasUser($user));
        $this->assertEmpty($user->groups()->get());
        $this->assertEmpty($user->abilities()->get());

        $team->deleteGroup($group->getKey());
        $team->deleteRole($role->getKey());
        $team->purge();

        $this->assertModelMissing($team);
        Event::assertDispatchedTimes(TeamCreated::class, 1);
        Event::assertDispatchedTimes(TeamUpdated::class, 1);
        Event::assertDispatchedTimes(TeamDeleted::class, 1);
        Event::assertDispatched(TeamCreated::class, fn ($event) => $event->team === $team);
    }

    public function test_invitations_resolve_and_use_the_independent_team_model(): void
    {
        Mail::fake();
        $team = $this->user()->ownedTeams()->create(['name' => 'Acme']);
        $team->addRole('editor', ['articles.edit']);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $invitation = $team->invitations()->firstOrFail();

        $this->assertInstanceOf(Workspace::class, $invitation->team);
        $invitation->team->inviteAccept($invitation->getKey());

        $this->assertTrue($team->hasUser($user));
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertModelMissing($invitation);
        Mail::assertSent(Invitation::class, 1);
    }
}

class Workspace extends Model
{
    use HasMembers;

    protected $table = 'workspaces';

    protected $fillable = ['user_id', 'name', 'code', 'description'];
}

class ScopedWorkspace extends Workspace
{
    public function permissions(): HasMany
    {
        return parent::permissions()->where('code', 'like', 'visible.%');
    }
}
