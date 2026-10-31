<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberInviting;
use Jurager\Teams\Mail\Invitation as InvitationMail;
use Jurager\Teams\Tests\Fixtures\User;

beforeEach(function () {
    Mail::fake();
    $this->freezeTime();
    config([
        'cache.default' => 'array',
        'cache.limiter' => 'array',
        'teams.invitations.rate_limit.enabled' => true,
    ]);
});

it('preserves resending when rate limiting is disabled or unconfigured', function (bool $configured) {
    config(['teams.invitations.rate_limit' => $configured ? ['enabled' => false] : []]);
    $team = team();
    role($team, 'reader');

    $team->invite('guest@example.com')->as('reader');
    $team->invite('guest@example.com')->as('reader');

    expect($team->invitations()->count())->toBe(1);
    Mail::assertSent(InvitationMail::class, 2);
})->with([false, true]);

it('uses default limits and durations when configuration is partial', function () {
    config(['teams.invitations.rate_limit' => ['enabled' => true, 'email' => ['max_attempts' => 2]]]);
    $team = team();
    role($team, 'reader');
    $team->invite('guest@example.com')->as('reader');
    $team->invite('guest@example.com')->as('reader');

    expect(fn () => $team->invite('guest@example.com')->as('reader'))->toThrow(ThrottleRequestsException::class);

    $this->travel(61)->seconds();
    $team->invite('guest@example.com')->as('reader');

    Mail::assertSent(InvitationMail::class, 3);
});

it('throttles an address without changing its invitation or dispatching events', function () {
    $team = team();
    role($team, 'reader');
    role($team, 'editor');
    $invitation = $team->invite('guest@example.com')->as('reader');
    $sentAt = $invitation->updated_at;
    $this->travel(10)->seconds();
    Mail::fake();
    Event::fake([TeamMemberInviting::class, TeamMemberInvited::class]);

    try {
        $team->invite('GUEST@example.com')->as('editor');
        $this->fail('Expected the email limit to reject the invitation.');
    } catch (ThrottleRequestsException $exception) {
        expect($exception->getStatusCode())->toBe(429)
            ->and($exception->getHeaders()['Retry-After'])->toBe(50);
    }

    expect($invitation->fresh()->role->code)->toBe('reader')
        ->and($invitation->fresh()->updated_at->eq($sentAt))->toBeTrue()
        ->and($team->invitations()->count())->toBe(1);
    Event::assertNotDispatched(TeamMemberInviting::class);
    Event::assertNotDispatched(TeamMemberInvited::class);
    Mail::assertNothingSent();
});

it('checks both limits before consuming the team allowance', function () {
    config(['teams.invitations.rate_limit.team.max_attempts' => 2]);
    $team = team();
    role($team, 'reader');
    $team->invite('first@example.com')->as('reader');

    expect(fn () => $team->invite('first@example.com')->as('reader'))->toThrow(ThrottleRequestsException::class);

    $team->invite('second@example.com')->as('reader');

    expect(fn () => $team->invite('third@example.com')->as('reader'))->toThrow(ThrottleRequestsException::class)
        ->and($team->invitations()->count())->toBe(2);
    Mail::assertSent(InvitationMail::class, 2);
});

it('expires the email and team windows independently using configured durations', function () {
    config([
        'teams.invitations.rate_limit.team.max_attempts' => 2,
        'teams.invitations.rate_limit.team.decay_seconds' => 120,
        'teams.invitations.rate_limit.email.decay_seconds' => 30,
    ]);
    $team = team();
    role($team, 'reader');
    $team->invite('guest@example.com')->as('reader');
    $this->travel(31)->seconds();
    $team->invite('guest@example.com')->as('reader');
    $this->travel(31)->seconds();

    expect(fn () => $team->invite('guest@example.com')->as('reader'))->toThrow(ThrottleRequestsException::class);

    $this->travel(60)->seconds();
    $team->invite('guest@example.com')->as('reader');

    Mail::assertSent(InvitationMail::class, 3);
});

it('isolates both limits by team', function () {
    config(['teams.invitations.rate_limit.team.max_attempts' => 1]);
    foreach ([team(), team()] as $team) {
        $team->invite('guest@example.com')->as(role($team, 'reader'));
    }

    Mail::assertSent(InvitationMail::class, 2);
});

it('uses the configured email allowance and the longest exhausted window for retrying', function () {
    config([
        'teams.invitations.rate_limit.team.max_attempts' => 2,
        'teams.invitations.rate_limit.team.decay_seconds' => 30,
        'teams.invitations.rate_limit.email.max_attempts' => 2,
        'teams.invitations.rate_limit.email.decay_seconds' => 60,
    ]);
    $team = team();
    role($team, 'reader');
    $team->invite('guest@example.com')->as('reader');
    $team->invite('guest@example.com')->as('reader');

    try {
        $team->invite('guest@example.com')->as('reader');
        $this->fail('Expected both limits to reject the invitation.');
    } catch (ThrottleRequestsException $exception) {
        expect($exception->getHeaders()['Retry-After'])->toBe(60);
    }

    Mail::assertSent(InvitationMail::class, 2);
});

it('isolates teams with the same key on different connections', function () {
    config(['database.connections.secondary' => config('database.connections.testing')]);
    $schema = Schema::getFacadeRoot();
    Schema::swap(DB::connection('secondary')->getSchemaBuilder());

    try {
        $this->createTables();
    } finally {
        Schema::swap($schema);
    }

    $first = team();
    $owner = User::on('secondary')->create(['email' => 'owner@example.com']);
    $second = $owner->teams()->create(['name' => 'Secondary']);
    expect($first->getKey())->toBe($second->getKey());

    foreach ([$first, $second] as $team) {
        $team->invite('guest@example.com')->as(role($team, 'reader'));
    }

    Mail::assertSent(InvitationMail::class, 2);
});

it('does not consume limits for invalid roles or existing members', function () {
    config(['teams.invitations.rate_limit.team.max_attempts' => 1]);
    $team = team();
    $reader = role($team, 'reader');
    $foreign = role(team(), 'editor');

    expect(fn () => $team->invite('guest@example.com')->as($foreign))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $team->invite($team->owner->email)->as($reader))->toThrow(RuntimeException::class);

    $team->invite('guest@example.com')->as($reader);

    Mail::assertSent(InvitationMail::class, 1);
});

it('counts validated attempts even when the database transaction rolls back', function () {
    $team = team();
    role($team, 'reader');
    Event::fake(TeamMemberInvited::class);

    expect(fn () => DB::transaction(function () use ($team) {
        $team->invite('guest@example.com')->as('reader');
        throw new RuntimeException('Rollback');
    }))->toThrow(RuntimeException::class, 'Rollback');

    expect($team->invitations()->count())->toBe(0)
        ->and(fn () => $team->invite('guest@example.com')->as('reader'))->toThrow(ThrottleRequestsException::class);
    Event::assertNotDispatched(TeamMemberInvited::class);
    Mail::assertNothingSent();
});
