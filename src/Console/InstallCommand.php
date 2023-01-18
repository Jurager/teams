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

	    // Models...
	    //if ($this->option('models')) {
		    //copy(__DIR__.'/../../stubs/app/Models/User.php', app_path('Models/User.php'));
		    //copy(__DIR__.'/../../stubs/app/Models/Team.php', app_path('Models/Team.php'));
	    //}

        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/app/Models', app_path('Models'));

	    // Directories...
	    (new Filesystem)->ensureDirectoryExists(app_path('Actions/Teams'));
	    (new Filesystem)->ensureDirectoryExists(app_path('Events'));
	    (new Filesystem)->ensureDirectoryExists(app_path('Policies'));

	    // Actions...
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/app/Actions/Teams', app_path('Actions/Teams/'));

	    // Policies...
	    (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/app/Policies', app_path('Policies'));
    }
}