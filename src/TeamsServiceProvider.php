<?php

namespace Jurager\Teams;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jurager\Teams\Facades\Teams as TeamsFacade;
use Jurager\Teams\Observers\MembershipObserver;
use Jurager\Teams\Traits\HasTeams;

class TeamsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfig();

        $this->app->singleton(Teams::class);
        $this->app->alias(Teams::class, 'teams');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'teams');

        TeamsFacade::model('membership')::observe(MembershipObserver::class);

        $this->registerGate();
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
     * Use the Gate for team permissions when no policy or ability did.
     */
    protected function registerGate(): void
    {
        if (! Config::get('teams.gate', true)) {
            return;
        }

        Gate::after(function (Authenticatable $user, $ability, $result, array $arguments) {

            $subject = $arguments[0] ?? null;

            if ($result !== null || ! is_string($ability) || ! $subject instanceof Model || ! in_array(HasTeams::class, class_uses_recursive($user), true)) {
                return null;
            }

            $team = $this->teamOf($subject);

            return $team?->member($user)->can($ability, $team === $subject ? null : $subject) ?: null;
        });
    }

    /**
     * Get the team itself, or the team of a record.
     */
    protected function teamOf(Model $subject): ?Model
    {
        $class = TeamsFacade::model('team');

        if ($subject instanceof $class) {
            return $subject;
        }

        $key = $subject->getAttribute(Config::get('teams.foreign_keys.team_id'));

        return $key === null ? null : $subject->belongsTo($class, Config::get('teams.foreign_keys.team_id'))->first();
    }

    /**
     * Register the invitation routes.
     */
    protected function registerRoutes(): void
    {
        if (! Config::get('teams.invitations.enabled') || ! Config::get('teams.invitations.routes.register')) {
            return;
        }

        Route::group([
            'prefix' => Config::get('teams.invitations.routes.prefix', '/'),
            'middleware' => Config::get('teams.invitations.routes.middleware'),
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

        // The prefixes are part of the published file names;
        // Changing one would republish that migration next to an already applied copy.
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
            'add_expires_at_to_entity_ability_table' => '2026_10_28_000001',
            'merge_ability_assignments' => '2026_10_28_000002',
        ];

        if (Config::get('teams.invitations.enabled')) {
            $migrations['create_invitations_table'] = '2019_12_14_000012';
        }

        $paths = [];

        foreach ($migrations as $name => $prefix) {
            $paths[__DIR__."/../database/migrations/{$name}.php"] = database_path("migrations/{$prefix}_{$name}.php");
        }

        $this->publishes($paths, 'teams-migrations');
    }
}
