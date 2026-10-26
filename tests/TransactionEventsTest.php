<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Events\TeamUpdated;

beforeEach(function () {
    Mail::fake();

    $this->team = team();
    role($this->team, 'reader');
    role($this->team, 'editor');

    $this->member = user();
    $this->team->add($this->member)->as('reader');
});

dataset('completed events', [
    'team created' => [TeamCreated::class, fn () => team()],
    'team updated' => [TeamUpdated::class, fn ($team) => $team->update(['name' => 'Renamed'])],
    'team deleted' => [TeamDeleted::class, fn () => team()->delete()],
    'member added' => [TeamMemberAdded::class, fn ($team) => $team->add(user())->as('editor')],
    'member updated' => [TeamMemberUpdated::class, fn ($team, $member) => $team->member($member)->assign('editor')],
    'member removed' => [TeamMemberRemoved::class, fn ($team, $member) => $team->remove($member)],
    'member invited' => [TeamMemberInvited::class, fn ($team) => $team->invite('invited@example.com')->as('editor')],
]);

it('dispatches completed events after the outermost commit', function (string $event, Closure $change) {
    $levels = [];
    Event::listen($event, function () use (&$levels) {
        $levels[] = DB::transactionLevel();
    });

    DB::transaction(function () use ($change, &$levels) {
        $change($this->team, $this->member);

        expect($levels)->toBe([]);
        Mail::assertNothingSent();
    });

    expect($levels)->toBe([0]);
})->with('completed events');

it('discards completed events on an outer rollback', function (string $event, Closure $change) {
    $dispatched = 0;
    Event::listen($event, function () use (&$dispatched) {
        $dispatched++;
    });

    DB::beginTransaction();
    $change($this->team, $this->member);
    DB::rollBack();

    expect($dispatched)->toBe(0);
    Mail::assertNothingSent();
})->with('completed events');
