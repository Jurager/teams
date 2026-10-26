<?php

use Jurager\Teams\Tests\TestCase;

beforeAll(fn () => TestCase::configure(['teams.gate' => false]));

it('can turn the gate off', function () {
    $team = team();
    $user = member($team, ['articles.edit']);

    expect($user->can('articles.edit', $team))->toBeFalse()
        ->and($team->member($user)->can('articles.edit'))->toBeTrue();
});
