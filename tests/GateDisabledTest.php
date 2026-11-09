<?php

use Illuminate\Support\Facades\Gate;
use Jurager\Teams\Facades\Teams;
use Jurager\Teams\Scope;
use Jurager\Teams\Tests\TestCase;

beforeAll(fn () => TestCase::configure(['teams.gate' => false]));

it('can turn the gate off', function () {
    $team = team();
    $user = member($team, ['articles.edit']);

    expect($user->can('articles.edit', $team))->toBeFalse()
        ->and($team->member($user)->can('articles.edit'))->toBeTrue();
});

it('still limits the application gate by the scope', function () {
    $user = member(team(), ['articles.edit']);
    Gate::define('publish', fn () => true);

    expect(Gate::forUser($user)->allows('publish'))->toBeTrue();

    Teams::scope(fn () => Scope::to('articles.edit'));

    expect(Gate::forUser($user)->allows('publish'))->toBeFalse();
});
