<?php

namespace Jurager\Teams\Tests;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\Article;

class CustomSchemaTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $tables = require __DIR__.'/../config/teams.php';

        foreach ($tables['tables'] as $key => $table) {
            $app['config']->set("teams.tables.{$key}", 'custom_'.$table);
        }

        $app['config']->set('teams.foreign_keys.team_id', 'workspace_id');
    }

    public function test_all_tables_foreign_keys_relations_and_rollback_use_configuration(): void
    {
        Mail::fake();
        $team = $this->team();
        $user = $this->member($team, ['articles.view']);
        $role = $user->teamRole($team);
        $this->assertTrue($role->team->is($team));
        $this->assertTrue($role->users->first()->is($user));
        $group = $team->addGroup('editors', ['articles.edit']);
        $this->assertTrue($group->attachUser($user));
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $article = Article::create(['workspace_id' => $team->id]);
        $user->allowTeamAbility($team, 'articles.publish', $article);
        $this->assertTrue($user->hasTeamAbility($team, 'articles.publish', $article));
        $this->assertTrue($team->abilities()->first()->team->is($team));
        $team->inviteUser('custom@example.com', $role->id);
        $this->assertTrue($team->invitations()->first()->team->is($team));
        $this->assertSame('custom_team_user', $user->teams()->first()->membership->getTable());
        $team->deleteUser($user);
        $team->deleteRole($role->id);
        $team->purge();
        $this->assertSame(0, Team::count());

        foreach ([
            'create_invitations_table',
            'create_entity_permission_table', 'create_group_user_table', 'create_groups_table',
            'create_entity_ability_table', 'create_abilities_table', 'create_team_user_table',
            'create_roles_table', 'create_permissions_table', 'create_teams_table',
        ] as $file) {
            (require __DIR__."/../database/migrations/{$file}.php")->down();
        }

        foreach (config('teams.tables') as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }
}
