<?php

namespace Jurager\Teams;

use Exception;
use Illuminate\Support\ServiceProvider;
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
        $this->registerMiddlewares();
        $this->registerModels();
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/teams.php' => config_path('teams.php'),
        ], 'teams-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('/migrations'),
        ], 'teams-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/teams'),
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
    protected function registerModels(): void
    {
        $models = ['user', 'team', 'ability', 'capability', 'group', 'invitation', 'membership', 'permission', 'role'];

        foreach ($models as $model) {

            if (! array_key_exists($model, config('teams.models'))) {
                throw new Exception('Error, missing '.$model.' model configuration');
            }

            if (! class_exists(config('teams.models.'.$model))) {
                throw new Exception('Error, configured model '.config('teams.models.'.$model).' not exists');
            }

            Teams::setModel($model, config('teams.models.'.$model));
        }
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
