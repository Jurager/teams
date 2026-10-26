<?php

use Illuminate\Support\ServiceProvider;
use Jurager\Teams\TeamsServiceProvider;

it('publishes the package assets', function (string $tag, int $count) {
    $paths = ServiceProvider::pathsToPublish(TeamsServiceProvider::class, $tag);

    expect($paths)->toHaveCount($count);

    foreach (array_keys($paths) as $source) {
        expect($source)->toBeReadableFile();
    }
})->with([
    'config' => ['teams-config', 1],
    'migrations' => ['teams-migrations', 10],
]);

it('publishes the views', function () {
    $paths = ServiceProvider::pathsToPublish(TeamsServiceProvider::class, 'teams-views');

    expect(array_key_first($paths).'/emails/invitation.blade.php')->toBeReadableFile();
});
