<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Models\AbilityAssignment;

it('expires allow and forbid rules for every recipient without refreshing loaded rules', function (string $recipient, bool $forbidden, bool $preload) {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team, $forbidden ? ['articles.edit'] : []));
    $article = article($team);
    $target = match ($recipient) {
        'user' => $member,
        'role' => $member->role(),
        'group' => $team->groups()->create(['code' => 'editors'])->add($member->user),
    };
    $expiresAt = now()->addMinute()->startOfSecond();
    $target->{$forbidden ? 'forbid' : 'allow'}('articles.edit')->until($expiresAt)->on($article);

    if ($preload) {
        $member->load([$article]);
        DB::enableQueryLog();
    }

    expect($member->can('articles.edit', $article))->toBe(! $forbidden)
        ->and($member->inspect('articles.edit', $article)->code())->toBe($recipient.($forbidden ? '_forbidden' : '_allowed'));

    $this->travelTo($expiresAt);

    expect($member->can('articles.edit', $article))->toBe($forbidden)
        ->and($member->canAny(['articles.edit'], $article))->toBe($forbidden)
        ->and($member->inspect('articles.edit', $article)->code())->toBe($forbidden ? 'role_permission' : 'missing_permission');

    if ($preload) {
        expect(DB::getQueryLog())->toBeEmpty();
    }
})->with(['user', 'role', 'group'])->with([false, true])->with([false, true]);

it('keeps independent expiration times on a shared ability definition', function () {
    $this->freezeTime();
    $team = team();
    $first = $team->member(member($team));
    $second = $team->member(member($team));
    $article = article($team);
    $first->allow('articles.edit')->until(now()->addMinute())->on($article);
    $second->allow('articles.edit')->until(now()->addHour())->on($article);
    $role = $first->role();
    $role->forbid('articles.edit')->on($article);

    $this->travel(2)->minutes();

    expect($team->abilities()->count())->toBe(1)
        ->and($first->can('articles.edit', $article))->toBeFalse()
        ->and($first->inspect('articles.edit', $article)->code())->toBe('role_forbidden')
        ->and($second->can('articles.edit', $article))->toBeTrue();
});

it('falls through expired personal rules to groups and roles', function (bool $preload) {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team, ['articles.*']));
    $article = article($team);
    $group = $team->groups()->create(['code' => 'reviewers'])->add($member->user);
    $group->forbid('articles.edit')->until(now()->addMinutes(2))->on($article);
    $member->allow('articles.edit')->until(now()->addMinute())->on($article);

    if ($preload) {
        $member->load([$article]);
    }

    expect($member->can('articles.edit', $article))->toBeTrue();
    $this->travel(1)->minutes();
    expect($member->inspect('articles.edit', $article)->code())->toBe('group_forbidden');
    $this->travel(1)->minutes();
    expect($member->inspect('articles.edit', $article)->code())->toBe('role_permission');
})->with([false, true]);

it('ignores expired forbids when another matching wildcard rule still allows', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.*')->until(now()->addHour())->on($article);
    $member->forbid('articles.edit')->until(now()->subSecond())->on($article);

    expect($member->inspect('articles.edit', $article)->code())->toBe('user_allowed');
});

it('can renew expired rules and replace a deadline with permanent access', function () {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->until(now()->subMinute())->on($article);
    expect($member->can('articles.edit', $article))->toBeFalse();

    $member->allow('articles.edit')->until(now()->addMinute())->on($article);
    expect($member->can('articles.edit', $article))->toBeTrue();

    $member->allow('articles.edit')->on($article);
    $this->travel(1)->days();

    expect($member->can('articles.edit', $article))->toBeTrue()
        ->and($member->user->abilities()->first()->pivot->expires_at)->toBeNull();
});

it('reports deadline changes, skips identical writes and retains the old deadline when removing a rule', function () {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $first = now()->addMinute()->startOfSecond();
    $second = now()->addHour()->startOfSecond();
    Event::fake(AbilityUpdated::class);

    $member->allow('articles.edit')->until($first)->on($article);
    $member->allow('articles.edit')->until($first)->on($article);
    $member->allow('articles.edit')->until($second)->on($article);
    $member->forget('articles.edit')->on($article);

    Event::assertDispatchedTimes(AbilityUpdated::class, 3);
    Event::assertDispatched(AbilityUpdated::class, fn ($event) => $event->previousExpiresAt === null && $event->expiresAt?->equalTo($first));
    Event::assertDispatched(AbilityUpdated::class, fn ($event) => $event->previous === false && $event->forbidden === false
        && $event->previousExpiresAt?->equalTo($first) && $event->expiresAt?->equalTo($second));
    Event::assertDispatched(AbilityUpdated::class, fn ($event) => $event->forbidden === null
        && $event->previousExpiresAt?->equalTo($second) && $event->expiresAt === null);
});

it('rolls back a deadline change and its event with the outer transaction', function () {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $deadline = now()->addHour()->startOfSecond();
    $member->allow('articles.edit')->until($deadline)->on($article);
    Event::fake(AbilityUpdated::class);

    DB::beginTransaction();
    $member->allow('articles.edit')->until(now()->addDay())->on($article);
    DB::rollBack();

    expect($member->user->abilities()->first()->pivot->expires_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($member->user->abilities()->first()->pivot->expires_at->equalTo($deadline))->toBeTrue();
    Event::assertNotDispatched(AbilityUpdated::class);
});

it('normalizes deadline timezones and precision without mutating the given date', function () {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $deadline = now()->addHour()->setTimezone('Asia/Tokyo')->setMicrosecond(123456);
    $original = $deadline->copy();
    Event::fake(AbilityUpdated::class);

    $member->allow('articles.edit')->until($deadline)->on($article);
    $member->allow('articles.edit')->until($deadline)->on($article);

    expect($deadline->equalTo($original))->toBeTrue()
        ->and($deadline->getTimezone()->getName())->toBe('Asia/Tokyo')
        ->and($member->can('articles.edit', $article))->toBeTrue();

    Event::assertDispatchedTimes(AbilityUpdated::class, 1);
    $this->travelTo($original->copy()->startOfSecond());
    expect($member->can('articles.edit', $article))->toBeFalse();
});

it('lists deadlines for each recipient when querying record rules', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $deadline = now()->addHour()->startOfSecond();
    $member->allow('articles.edit')->until($deadline)->on($article);
    $member->role()->forbid('articles.edit')->until($deadline)->on($article);
    $team->groups()->create(['code' => 'reviewers'])->allow('articles.edit')->until($deadline)->on($article);
    $ability = $team->rules($article)->firstOrFail();

    foreach (['users', 'roles', 'groups'] as $relation) {
        $pivot = $ability->$relation->first()->pivot;

        expect($pivot)->toBeInstanceOf(AbilityAssignment::class)
            ->and($pivot->expires_at)->toBeInstanceOf(CarbonInterface::class)
            ->and($pivot->expires_at->equalTo($deadline))->toBeTrue();
    }
});

it('adds the expiry column to existing assignments without changing permanent rules', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on($article);
    $migration = require __DIR__.'/../database/migrations/add_expires_at_to_entity_ability_table.php';

    $migration->down();
    expect(Schema::hasColumn(config('teams.tables.entity_ability'), 'expires_at'))->toBeFalse();
    $migration->up();

    expect($member->can('articles.edit', $article))->toBeTrue()
        ->and($member->user->abilities()->first()->pivot->expires_at)->toBeNull();
});

it('does not put a deadline on forgetting a rule', function () {
    team()->member(user())->forget('articles.edit')->until(now()->addHour());
})->throws(LogicException::class);

it('rolls back a rule assignment when its pivot save is cancelled', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    Event::fake(AbilityUpdated::class);
    AbilityAssignment::saving(fn () => false);

    expect(fn () => $member->allow('articles.edit')->until(now()->addHour())->on($article))->toThrow(RuntimeException::class)
        ->and($team->abilities()->count())->toBe(0)
        ->and($member->can('articles.edit', $article))->toBeFalse();

    Event::assertNotDispatched(AbilityUpdated::class);
});

it('preserves a forbid and its deadline when its pivot update is cancelled', function () {
    $team = team();
    $member = $team->member(member($team, ['articles.*']));
    $article = article($team);
    $deadline = now()->addHour()->startOfSecond();
    $member->forbid('articles.edit')->until($deadline)->on($article);
    Event::fake(AbilityUpdated::class);
    AbilityAssignment::saving(fn () => false);

    expect(fn () => $member->allow('articles.edit')->until(now()->addDay())->on($article))->toThrow(RuntimeException::class)
        ->and($member->can('articles.edit', $article))->toBeFalse()
        ->and($member->user->abilities()->first()->pivot->expires_at->equalTo($deadline))->toBeTrue();

    Event::assertNotDispatched(AbilityUpdated::class);
});

it('preserves a rule when its pivot deletion is cancelled', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->until(now()->addHour())->on($article);
    Event::fake(AbilityUpdated::class);
    AbilityAssignment::deleting(fn () => false);

    expect(fn () => $member->forget('articles.edit')->on($article))->toThrow(RuntimeException::class)
        ->and($member->can('articles.edit', $article))->toBeTrue();

    Event::assertNotDispatched(AbilityUpdated::class);
});
