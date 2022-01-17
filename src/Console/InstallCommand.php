<?php

namespace Jurager\Teams\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:install {--models : Indicates if user and team models should be installed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the teams components and resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // Publish...
        $this->callSilent('vendor:publish', ['--tag' => 'teams-config', '--force' => true]);
        $this->callSilent('vendor:publish', ['--tag' => 'teams-migrations', '--force' => true]);

	    // Service Providers...
	    copy(__DIR__.'/../../stubs/app/Providers/TeamsServiceProvider.php', app_path('Providers/TeamsServiceProvider.php'));
	    copy(__DIR__.'/../../stubs/app/Providers/AuthServiceProvider.php', app_path('Providers/AuthServiceProvider.php'));

	    // Models...
	    if ($this->option('models')) {
		    copy(__DIR__.'/../../stubs/app/Models/User.php', app_path('Models/User.php'));
		    copy(__DIR__.'/../../stubs/app/Models/Team.php', app_path('Models/Team.php'));
	    }

	    copy(__DIR__.'/../../stubs/app/Models/Ability.php', app_path('Models/Ability.php'));
	    copy(__DIR__.'/../../stubs/app/Models/Capability.php', app_path('Models/Capability.php'));
	    copy(__DIR__.'/../../stubs/app/Models/Permission.php', app_path('Models/Permission.php'));
	    copy(__DIR__.'/../../stubs/app/Models/Membership.php', app_path('Models/Membership.php'));
	    copy(__DIR__.'/../../stubs/app/Models/Invitation.php', app_path('Models/Invitation.php'));
	    copy(__DIR__.'/../../stubs/app/Models/Role.php', app_path('Models/Role.php'));

	    // Directories...
	    (new Filesystem)->ensureDirectoryExists(app_path('Actions/Teams'));
	    (new Filesystem)->ensureDirectoryExists(app_path('Events'));
	    (new Filesystem)->ensureDirectoryExists(app_path('Policies'));

	    // Actions...
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/DeleteUser.php', app_path('Actions/Teams/DeleteUser.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/AddTeamMember.php', app_path('Actions/Teams/AddTeamMember.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/CreateTeam.php', app_path('Actions/Teams/CreateTeam.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/DeleteTeam.php', app_path('Actions/Teams/DeleteTeam.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/DeleteUser.php', app_path('Actions/Teams/DeleteUser.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/InviteTeamMember.php', app_path('Actions/Teams/InviteTeamMember.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/RemoveTeamMember.php', app_path('Actions/Teams/RemoveTeamMember.php'));
	    copy(__DIR__.'/../../stubs/app/Actions/Teams/UpdateTeamName.php', app_path('Actions/Teams/UpdateTeamName.php'));


	    // Policies...
	    (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/app/Policies', app_path('Policies'));

	    // Factories...
	    //copy(__DIR__.'/../../database/factories/UserFactory.php', base_path('database/factories/UserFactory.php'));
	    //copy(__DIR__.'/../../database/factories/TeamFactory.php', base_path('database/factories/TeamFactory.php'));
    }
}