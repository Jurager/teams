<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Models\Ability;
use Jurager\Teams\Models\Permission;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\TeamsServiceProvider;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;

class IntegrityTest extends TestCase
{
    public function test_duplicate_group_memberships_are_rejected_by_the_database(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $group = $team->addGroup('editors');
        $group->attachUser($user);
        $this->expectException(QueryException::class);
        $group->users()->attach($user);
    }

    public function test_duplicate_ability_definitions_are_rejected_by_the_database(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $article = Article::create(['team_id' => $team->id]);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $attributes = $team->abilities()->first()->getAttributes();
        unset($attributes['id']);
        $this->expectException(QueryException::class);
        DB::table(config('teams.tables.abilities'))->insert($attributes);
    }

    public function test_duplicate_ability_assignments_are_rejected_by_the_database(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $article = Article::create(['team_id' => $team->id]);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $attributes = (array) DB::table(config('teams.tables.entity_ability'))->first();
        unset($attributes['id']);
        $this->expectException(QueryException::class);
        DB::table(config('teams.tables.entity_ability'))->insert($attributes);
    }

    public function test_partial_legacy_configuration_preserves_new_defaults(): void
    {
        config(['teams.tables' => ['teams' => 'teams', 'team_user' => 'team_user']]);
        config(['teams.models' => ['user' => User::class]]);
        (new TeamsServiceProvider($this->app))->register();
        $this->assertSame('entity_ability', config('teams.tables.entity_ability'));
        $this->assertSame(Role::class, Teams::model('role'));
        $this->assertSame(User::class, Teams::model('user'));
    }

    public function test_custom_model_names_do_not_change_pivot_foreign_keys(): void
    {
        config(['teams.models.role' => CustomRole::class]);
        config(['teams.models.ability' => CustomAbility::class]);
        config(['teams.models.permission' => CustomPermission::class]);
        $team = $this->team();
        $user = $this->member($team, ['articles.view']);
        $this->assertInstanceOf(CustomRole::class, $user->teamRole($team));
        $this->assertTrue($user->hasTeamPermission($team, 'articles.view'));
        $article = Article::create(['team_id' => $team->id]);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertTrue($team->abilities()->first()->users->first()->is($user));
    }

    public function test_successful_acceptance_consumes_the_invitation(): void
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('editor', ['articles.edit']);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $team->inviteAccept($team->invitations()->first()->id);
        $this->assertTrue($team->hasUser($user));
        $this->assertSame(0, $team->invitations()->count());
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
    }

    public function test_ability_queries_do_not_grow_with_the_number_of_groups(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $article = Article::create(['team_id' => $team->id]);

        for ($i = 0; $i < 5; $i++) {
            $team->addGroup('group-'.$i, ['articles.view'])->attachUser($user);
        }

        DB::enableQueryLog();
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertLessThanOrEqual(7, count(DB::getQueryLog()));
    }
}

class CustomRole extends Role {}
class CustomAbility extends Ability {}
class CustomPermission extends Permission {}
