<?php

namespace Jurager\Teams;

use Exception;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jurager\Teams\Support\Services\TeamsService;
use Jurager\Teams\Middleware\Ability as AbilityMiddleware;
use Jurager\Teams\Middleware\Permission as PermissionMiddleware;
use Jurager\Teams\Middleware\Role as RoleMiddleware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class TeamsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');
    }

    /**
     * Bootstrap any application services.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'teams');

        $this->configureCommands();
        $this->configurePublishing();
        $this->registerFacades();
        $this->registerMiddlewares();

        if(config('teams.invitations.enabled') && config('teams.invitations.routes.register')) {
            $this->registerRoutes();
        }
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $migrations = [
            __DIR__ . '/../database/migrations/create_teams_table.php' => database_path('migrations/2019_12_14_000001_create_teams_table.php'),
            __DIR__ . '/../database/migrations/create_permissions_table.php' => database_path('migrations/2019_12_14_000002_create_permissions_table.php'),
            __DIR__ . '/../database/migrations/create_roles_table.php' => database_path('migrations/2019_12_14_000003_create_roles_table.php'),
            __DIR__ . '/../database/migrations/create_team_user_table.php' => database_path('migrations/2019_12_14_000005_create_team_user_table.php'),
            __DIR__ . '/../database/migrations/create_abilities_table.php' => database_path('migrations/2019_12_14_000006_create_abilities_table.php'),
            __DIR__ . '/../database/migrations/create_entity_ability_table.php' => database_path('migrations/2019_12_14_000006_create_entity_ability_table.php'),
            __DIR__ . '/../database/migrations/create_groups_table.php' => database_path('migrations/2019_12_14_000008_create_groups_table.php'),
            __DIR__ . '/../database/migrations/create_group_user_table.php' => database_path('migrations/2019_12_14_000009_create_group_user_table.php'),
            __DIR__ . '/../database/migrations/create_entity_permission_table.php' => database_path('migrations/2019_12_14_000010_create_entity_permission_table.php'),
        ];

        if(config('teams.invitations.enabled')) {
            $migrations[__DIR__ . '/../database/migrations/create_invitations_table.php'] = database_path('migrations/2019_12_14_000012_create_invitations_table.php');
        }

        $this->publishes([
            __DIR__.'/../config/teams.php' => config_path('teams.php')
        ], 'teams-config');

        $this->publishes($migrations, 'teams-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/teams')
        ], 'teams-views');
    }

    /**
     * Configure the commands offered by the application.
     */
    protected function configureCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([Console\InstallCommand::class]);
    }

    /**
     * Register the models offered by the application.
     *
     * @throws Exception
     */
    protected function registerFacades(): void
    {
        $this->app->singleton('teams', static function () {
            return new TeamsService;
        });
    }

    /**
     * @return void
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('teams.routes.prefix', '/'),
            'middleware' => config('teams.routes.middleware', 'web'),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register the middlewares automatically.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function registerMiddlewares(): void
    {
        if (! $this->app['config']->get('teams.middleware.register')) {
            return;
        }

        $middlewares = [
            'ability' => AbilityMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ];

        foreach ($middlewares as $key => $class) {
            $this->app['router']->aliasMiddleware($key, $class);
        }
    }
}
