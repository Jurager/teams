<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\TeamsServiceProvider;
use Jurager\Teams\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Configuration applied before the application boots, set for one test file.
     *
     * @var array<string, mixed>
     */
    protected static array $config = [];

    /**
     * Use the given configuration for the tests of the current file.
     */
    public static function configure(array $config): void
    {
        static::$config = $config;
    }

    public static function tearDownAfterClass(): void
    {
        static::$config = [];

        parent::tearDownAfterClass();
    }

    protected function getPackageProviders($app): array
    {
        return [TeamsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'app.key' => str_repeat('a', 32),
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
            'mail.default' => 'array',
            'teams.models.user' => User::class,
            ...static::$config,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    protected function createTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(config('teams.foreign_keys.team_id'));
            $table->unsignedBigInteger('owner_id')->nullable();
        });

        foreach ([
            'create_teams_table', 'create_permissions_table', 'create_roles_table',
            'create_team_user_table', 'create_abilities_table', 'create_entity_ability_table',
            'create_groups_table', 'create_group_user_table', 'create_entity_permission_table',
            'create_invitations_table',
        ] as $migration) {
            (require __DIR__."/../database/migrations/{$migration}.php")->up();
        }
    }
}
