<?php

namespace Jurager\Teams;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jurager\Teams\Middleware\Ability as AbilityMiddleware;
use Jurager\Teams\Middleware\Permission as PermissionMiddleware;
use Jurager\Teams\Middleware\Role as RoleMiddleware;
use Jurager\Teams\Observers\GroupObserver;
use Jurager\Teams\Observers\MembershipObserver;
use Jurager\Teams\Observers\PermissionCacheObserver;
use Jurager\Teams\Observers\RoleObserver;
use Jurager\Teams\Observers\TeamObserver;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Support\Services\TeamsService;

class TeamsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfig();

        $this->app->scoped(TeamsService::class);
        $this->app->alias(TeamsService::class, 'teams');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'teams');

        $this->registerObservers();
        $this->registerCacheInvalidation();
        $this->registerMiddlewares();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * Merge package defaults into the application config.
     */
    protected function mergeConfig(): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $defaults = require __DIR__.'/../config/teams.php';

        $config->set('teams', array_replace($defaults, $config->get('teams', [])));

        foreach (['models', 'tables', 'foreign_keys'] as $key) {
            $config->set("teams.{$key}", array_replace($defaults[$key] ?? [], $config->get("teams.{$key}", [])));
        }
    }

    /**
     * Observe the configured models.
     */
    protected function registerObservers(): void
    {
        foreach ([
            'team' => [PermissionCacheObserver::class, TeamObserver::class],
            'role' => [PermissionCacheObserver::class, RoleObserver::class],
            'group' => [PermissionCacheObserver::class, GroupObserver::class],
            'permission' => PermissionCacheObserver::class,
            'membership' => [PermissionCacheObserver::class, MembershipObserver::class],
        ] as $model => $observer) {
            Teams::model($model)::observe($observer);
        }
    }

    /**
     * Clear decisions before after-commit listeners run, or after rollback.
     */
    protected function registerCacheInvalidation(): void
    {
        Event::listen(
            [TransactionCommitting::class, TransactionRolledBack::class],
            fn () => Teams::forgetCachedPermissions(),
        );
    }

    /**
     * Register the middleware aliases.
     */
    protected function registerMiddlewares(): void
    {
        if (! $this->app->make('config')->get('teams.middleware.register')) {
            return;
        }

        Route::aliasMiddleware('ability', AbilityMiddleware::class);
        Route::aliasMiddleware('role', RoleMiddleware::class);
        Route::aliasMiddleware('permission', PermissionMiddleware::class);
    }

    /**
     * Register the invitation routes.
     */
    protected function registerRoutes(): void
    {
        $config = $this->app->make('config');

        if (! $config->get('teams.invitations.enabled') || ! $config->get('teams.invitations.routes.register')) {
            return;
        }

        Route::group([
            'prefix' => $config->get('teams.invitations.routes.prefix', '/'),
            'middleware' => $config->get('teams.invitations.routes.middleware'),
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }

    /**
     * Register the publishable resources.
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/teams.php' => config_path('teams.php'),
        ], 'teams-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/teams'),
        ], 'teams-views');

        // The prefixes are part of the published file names; changing one would
        // republish that migration next to an already applied copy.
        $migrations = [
            'create_teams_table' => '2019_12_14_000001',
            'create_permissions_table' => '2019_12_14_000002',
            'create_roles_table' => '2019_12_14_000003',
            'create_team_user_table' => '2019_12_14_000005',
            'create_abilities_table' => '2019_12_14_000006',
            'create_entity_ability_table' => '2019_12_14_000006',
            'create_groups_table' => '2019_12_14_000008',
            'create_group_user_table' => '2019_12_14_000009',
            'create_entity_permission_table' => '2019_12_14_000010',
        ];

        if ($this->app->make('config')->get('teams.invitations.enabled')) {
            $migrations['create_invitations_table'] = '2019_12_14_000012';
        }

        $paths = [];

        foreach ($migrations as $name => $prefix) {
            $paths[__DIR__."/../database/migrations/{$name}.php"] = database_path("migrations/{$prefix}_{$name}.php");
        }

        $this->publishes($paths, 'teams-migrations');
    }
}
