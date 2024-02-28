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
    protected $signature = 'teams:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the teams package migrations and creates additional directories';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        if (!$this->confirm('Do you wish to continue?')) {
            return;
        }

        // Publish...
        $this->call('vendor:publish', ['--tag' => 'teams-config', '--force' => true]);
        $this->call('vendor:publish', ['--tag' => 'teams-migrations', '--force' => true]);
        $this->call('vendor:publish', ['--tag' => 'teams-views', '--force' => true]);

        // Directories...
        (new Filesystem)->ensureDirectoryExists(app_path('Actions/Teams'));
        (new Filesystem)->ensureDirectoryExists(app_path('Events'));
        (new Filesystem)->ensureDirectoryExists(app_path('Policies'));

        // Actions...
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/app/Actions/Teams', app_path('Actions/Teams/'));

        // Policies...
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/app/Policies', app_path('Policies'));

        $this->info('All done. Have a nice journey.');
    }
}