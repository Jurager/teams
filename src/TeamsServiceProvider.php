<?php

namespace Jurager\Teams;

use Illuminate\Support\ServiceProvider;

class TeamsServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->loadViewsFrom(__DIR__.'/../resources/views', 'teams');

		$this->configurePublishing();
		$this->configureCommands();
		$this->registerMiddlewares();
	}

	/**
	 * Configure publishing for the package.
	 *
	 * @return void
	 */
	protected function configurePublishing()
	{
		if (! $this->app->runningInConsole()) {
			return;
		}

		$this->publishes([
			__DIR__.'/../resources/views' => resource_path('views/vendor/teams'),
		], 'teams-views');

		$this->publishes([
			__DIR__.'/../config/teams.php' => config_path('teams.php')
		], 'teams-config');

		$this->publishes([
			__DIR__.'/../database/migrations/2014_10_12_000000_create_users_table.php'           => database_path('migrations/2014_10_12_000000_create_users_table.php'),
			__DIR__.'/../database/migrations/2019_01_19_100000_create_teams_table.php'           => database_path('migrations/2019_01_19_100000_create_teams_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_300000_create_invitations_table.php'     => database_path('migrations/2020_05_21_300000_create_invitations_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_300000_create_abilities_table.php'       => database_path('migrations/2020_05_21_300000_create_abilities_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_300000_create_permissions_table.php'     => database_path('migrations/2020_05_21_300000_create_permissions_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_100000_create_capabilities_table.php'    => database_path('migrations/2020_05_21_100000_create_capabilities_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_100000_create_roles_table.php'           => database_path('migrations/2020_05_21_100000_create_roles_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_200000_create_team_user_table.php'       => database_path('migrations/2020_05_21_200000_create_team_user_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_150000_create_role_capability_table.php' => database_path('migrations/2020_05_21_150000_create_role_capability_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_300000_create_team_groups_table.php'     => database_path('migrations/2020_05_21_300000_create_team_groups_table.php'),
			__DIR__.'/../database/migrations/2020_05_21_300000_create_user_group_table.php'      => database_path('migrations/2020_05_21_300000_create_user_group_table.php')
		], 'teams-migrations');
	}

	/**
	 * Configure the commands offered by the application.
	 *
	 * @return void
	 */
	protected function configureCommands()
	{
		if (! $this->app->runningInConsole()) {
			return;
		}

		$this->commands([ Console\InstallCommand::class ]);
	}

	/**
	 * Register the middlewares automatically.
	 *
	 * @return void
	 */
	protected function registerMiddlewares()
	{
		if (!$this->app['config']->get('teams.middleware.register')) {
			return;
		}

		$middlewares = [
			'ability'    => \Jurager\Teams\Middleware\Ability::class,
			'role'       => \Jurager\Teams\Middleware\Role::class,
			'permission' => \Jurager\Teams\Middleware\Permission::class,
		];

		foreach ($middlewares as $key => $class) {
            $this->app['router']->aliasMiddleware($key, $class);
		}
	}
}
