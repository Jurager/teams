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
        $this->mergeConfigFrom(__DIR__ . "/../config/teams.php", "teams");
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
        $this->loadViewsFrom(__DIR__ . "/../resources/views", "teams");

        $this->configurePublishing();
        $this->configureCommands();
        $this->registerMiddlewares();

        Teams::useTeamModel(config("teams.models.team", \Jurager\Teams\Models\Team::class));
        Teams::useAbilityModel(config("teams.models.ability", \Jurager\Teams\Models\Ability::class));
        Teams::useCapabilityModel(config("teams.models.capability", \Jurager\Teams\Models\Capability::class));
        Teams::useGroupModel(config("teams.models.group", \Jurager\Teams\Models\Group::class));
        Teams::useInvitationModel(config("teams.models.invitation", \Jurager\Teams\Models\Invitation::class));
        Teams::useMembershipModel(config("teams.models.membership", \Jurager\Teams\Models\Membership::class));
        Teams::usePermissionModel(config("teams.models.permission", \Jurager\Teams\Models\Permission::class));
        Teams::useRoleModel(config("teams.models.role", \Jurager\Teams\Models\Role::class));

        //Teams::useUserModel(config("teams.models.user", \App\Models\User::class));

        Teams::createTeamsUsing(\App\Actions\Teams\CreateTeam::class);
        Teams::updateTeamNamesUsing(\App\Actions\Teams\UpdateTeamName::class);
        Teams::addTeamMembersUsing(\App\Actions\Teams\AddTeamMember::class);
        Teams::inviteTeamMembersUsing(\App\Actions\Teams\InviteTeamMember::class);
        Teams::removeTeamMembersUsing(\App\Actions\Teams\RemoveTeamMember::class);
        Teams::deleteTeamsUsing(\App\Actions\Teams\DeleteTeam::class);
        Teams::deleteUsersUsing(\App\Actions\Teams\DeleteUser::class);
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
            __DIR__ . "/../resources/views" => resource_path("views/vendor/teams")
        ],"teams-views");

        $this->publishes([
            __DIR__ . "/../config/teams.php" => config_path("teams.php")
        ],"teams-config");

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('/migrations')
        ], 'teams-migrations');
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
     * Register the middlewares automatically.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function registerMiddlewares(): void
    {
        if (!$this->app["config"]->get("teams.middleware.register")) {
            return;
        }

        $middlewares = [
            "ability" => \Jurager\Teams\Middleware\Ability::class,
            "role" => \Jurager\Teams\Middleware\Role::class,
            "permission" => \Jurager\Teams\Middleware\Permission::class,
        ];

        foreach ($middlewares as $key => $class) {
            $this->app["router"]->aliasMiddleware($key, $class);
        }
    }
}
