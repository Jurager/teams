<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Team;
use Jurager\Teams\TeamsServiceProvider;
use Jurager\Teams\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TeamsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', str_repeat('a', 32));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $app['config']->set('teams.models.user', User::class);
        $app['config']->set('mail.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        });
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(config('teams.foreign_keys.team_id'));
        });

        foreach ([
            'create_teams_table', 'create_permissions_table', 'create_roles_table',
            'create_team_user_table', 'create_abilities_table', 'create_entity_ability_table',
            'create_groups_table', 'create_group_user_table', 'create_entity_permission_table',
            'create_invitations_table',
        ] as $file) {
            (require __DIR__."/../database/migrations/{$file}.php")->up();
        }
    }

    protected function user(): User
    {
        return User::create(['email' => uniqid('member', true).'@example.com']);
    }

    protected function team(): Team
    {
        return Team::create(['user_id' => $this->user()->getKey(), 'name' => 'Example']);
    }

    protected function member(Team $team, array $permissions = []): User
    {
        $user = $this->user();
        $role = $team->addRole('member-'.$user->getKey(), $permissions);
        $team->addUser($user, $role->getKey());

        return $user;
    }
}
