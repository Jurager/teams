<?php

namespace Jurager\Teams\Tests;

use Illuminate\Filesystem\Filesystem;
use Jurager\Teams\TeamsServiceProvider;
use Orchestra\Testbench\TestCase;

class PublishingTest extends TestCase
{
    private string $directory;

    protected function getPackageProviders($app): array
    {
        return [TeamsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->directory = sys_get_temp_dir().'/teams-publish-'.bin2hex(random_bytes(8));
        (new Filesystem)->ensureDirectoryExists($this->directory);
        $app->setBasePath($this->directory);
        $app->useAppPath($this->directory.'/app');
        $app->useConfigPath($this->directory.'/config');
        $app->useDatabasePath($this->directory.'/database');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->directory) && str_starts_with($this->directory, sys_get_temp_dir().'/teams-publish-')) {
            (new Filesystem)->deleteDirectory($this->directory);
        }
    }

    public function test_package_assets_can_be_published_with_laravel(): void
    {
        foreach (['teams-config', 'teams-migrations', 'teams-views'] as $tag) {
            $this->artisan('vendor:publish', ['--tag' => $tag])->assertExitCode(0);
        }

        $this->assertFileExists($this->directory.'/config/teams.php');
        $this->assertCount(10, (new Filesystem)->files($this->directory.'/database/migrations'));
        $this->assertFileExists($this->directory.'/resources/views/vendor/teams/emails/invitation.blade.php');
    }

    public function test_publishing_preserves_existing_configuration(): void
    {
        $files = new Filesystem;
        $path = $this->directory.'/config/teams.php';
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, 'custom configuration');
        $this->artisan('vendor:publish', ['--tag' => 'teams-config'])->assertExitCode(0);

        $this->assertSame('custom configuration', $files->get($path));
    }
}
