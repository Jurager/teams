<?php

namespace Jurager\Teams;

use Exception;
use Jurager\Teams\Models\Ability as AbilityModel;
use Jurager\Teams\Models\Capability as CapabilityModel;
use Jurager\Teams\Models\Invitation as InvitationModel;
use Jurager\Teams\Models\Membership as MembershipModel;
use Jurager\Teams\Models\Permission as PermissionModel;
use Jurager\Teams\Middleware\Ability as AbilityMiddleware;
use Jurager\Teams\Middleware\Permission as PermissionMiddleware;
use Jurager\Teams\Middleware\Role as RoleMiddleware;
use Jurager\Teams\Models\Role as RoleModel;
use Jurager\Teams\Models\Team as TeamModel;
use Jurager\Teams\Models\TeamGroup as TeamGroupModel;
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
     * @throws Exception
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

    /**
     * Register the models offered by the application.
     *
     * @throws Exception
     */
    protected function registerModels(): void
    {
        if(!class_exists(config('teams.models.user'))) {
           throw new Exception('Please check that user model in config/teams.php is exists');
        }

        Teams::setModel('user', config('teams.models.user'));
        Teams::setModel('team', config('teams.models.team', TeamModel::class));
        Teams::setModel('ability', config('teams.models.ability', AbilityModel::class));
        Teams::setModel('capability', config('teams.models.capability', CapabilityModel::class));
        Teams::setModel('group', config('teams.models.group', TeamGroupModel::class));
        Teams::setModel('invitation', config('teams.models.invitation', InvitationModel::class));
        Teams::setModel('membership', config('teams.models.membership', MembershipModel::class));
        Teams::setModel('permission', config('teams.models.permission', PermissionModel::class));
        Teams::setModel('role', config('teams.models.role', RoleModel::class));
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
            'ability' => AbilityMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ];

        foreach ($middlewares as $key => $class) {
            $this->app['router']->aliasMiddleware($key, $class);
        }
    }
}
