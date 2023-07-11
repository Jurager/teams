<?php

namespace Jurager\Teams;

use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class TeamsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/teams.php', 'teams');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'teams');

        $this->configureCommands();
        $this->configurePublishing();
        $this->registerMiddlewares();
        $this->registerModels();
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/teams.php' => config_path('teams.php')
        ], 'teams-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('/migrations')
        ], 'teams-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/teams')
        ], 'teams-views');
    }

    /**
     * Configure the commands offered by the application.
     *
     * @return void
     */
    protected function configureCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([Console\InstallCommand::class]);
    }

    protected function registerModels(): void
    {
        if(!class_exists(config('teams.models.user'))) {
           throw new \Exception('Please check that user model in config/teams.php is exists');
        }

        Teams::setModel('user', config('teams.models.user'));
        Teams::setModel('team', config('teams.models.team', \Jurager\Teams\Models\Team::class));
        Teams::setModel('ability', config('teams.models.ability', \Jurager\Teams\Models\Ability::class));
        Teams::setModel('capability', config('teams.models.capability', \Jurager\Teams\Models\Capability::class));
        Teams::setModel('group', config('teams.models.group', \Jurager\Teams\Models\TeamGroup::class));
        Teams::setModel('invitation', config('teams.models.invitation', \Jurager\Teams\Models\Invitation::class));
        Teams::setModel('membership', config('teams.models.membership', \Jurager\Teams\Models\Membership::class));
        Teams::setModel('permission', config('teams.models.permission', \Jurager\Teams\Models\Permission::class));
        Teams::setModel('role', config('teams.models.role', \Jurager\Teams\Models\Role::class));
    }

    /**
     * Register the middlewares automatically.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function registerMiddlewares(): void
    {
        if (!$this->app['config']->get('teams.middleware.register')) {
            return;
        }

        $middlewares = [
            'ability' => \Jurager\Teams\Middleware\Ability::class,
            'role' => \Jurager\Teams\Middleware\Role::class,
            'permission' => \Jurager\Teams\Middleware\Permission::class,
        ];

        foreach ($middlewares as $key => $class) {
            $this->app['router']->aliasMiddleware($key, $class);
        }
    }
}
