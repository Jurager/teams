<?php

use Jurager\Teams\Tests\Fixtures\RestrictedMembership;
use Jurager\Teams\Tests\TestCase;

beforeAll(fn () => TestCase::configure(['teams.models.membership' => RestrictedMembership::class]));

it('runs custom membership validation on creation and role changes', function () {
    $team = team();
    role($team, 'reader');
    role($team, 'blocked');
    $user = user();

    expect(fn () => $team->add($user)->as('blocked'))->toThrow(RuntimeException::class, 'Blocked role.')
        ->and($user->in($team))->toBeFalse();

    $team->add($user)->as('reader');

    expect($user->memberships()->firstOrFail()->membership)->toBeInstanceOf(RestrictedMembership::class)
        ->and(fn () => $team->member($user)->assign('blocked'))->toThrow(RuntimeException::class, 'Blocked role.')
        ->and($team->member($user)->is('reader'))->toBeTrue();
});
