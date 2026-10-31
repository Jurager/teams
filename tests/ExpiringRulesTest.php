<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Models\Ability;

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

it('keeps independent expiration times on a record and permission', function () {
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

    expect($team->abilities()->count())->toBe(3)
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
        ->and($member->user->abilities()->first()->expires_at)->toBeNull();
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

    expect($member->user->abilities()->first()->expires_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($member->user->abilities()->first()->expires_at->equalTo($deadline))->toBeTrue();
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
    $abilities = $team->rules($article)->get();

    expect($abilities)->toHaveCount(3);

    foreach ($abilities as $ability) {
        expect($ability->recipient)->not->toBeNull()
            ->and($ability->expires_at)->toBeInstanceOf(CarbonInterface::class)
            ->and($ability->expires_at->equalTo($deadline))->toBeTrue();
    }
});

it('does not put a deadline on forgetting a rule', function () {
    team()->member(user())->forget('articles.edit')->until(now()->addHour());
})->throws(LogicException::class);

it('rolls back a rule assignment when its save is cancelled', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    Event::fake(AbilityUpdated::class);
    Ability::saving(fn () => false);

    expect(fn () => $member->allow('articles.edit')->until(now()->addHour())->on($article))->toThrow(RuntimeException::class)
        ->and($team->abilities()->count())->toBe(0)
        ->and($member->can('articles.edit', $article))->toBeFalse();

    Event::assertNotDispatched(AbilityUpdated::class);
});

it('preserves a forbid and its deadline when its update is cancelled', function () {
    $team = team();
    $member = $team->member(member($team, ['articles.*']));
    $article = article($team);
    $deadline = now()->addHour()->startOfSecond();
    $member->forbid('articles.edit')->until($deadline)->on($article);
    Event::fake(AbilityUpdated::class);
    Ability::saving(fn () => false);

    expect(fn () => $member->allow('articles.edit')->until(now()->addDay())->on($article))->toThrow(RuntimeException::class)
        ->and($member->can('articles.edit', $article))->toBeFalse()
        ->and($member->user->abilities()->first()->expires_at->equalTo($deadline))->toBeTrue();

    Event::assertNotDispatched(AbilityUpdated::class);
});

it('preserves a rule when its deletion is cancelled', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->until(now()->addHour())->on($article);
    Event::fake(AbilityUpdated::class);
    Ability::deleting(fn () => false);

    expect(fn () => $member->forget('articles.edit')->on($article))->toThrow(RuntimeException::class)
        ->and($member->can('articles.edit', $article))->toBeTrue();

    Event::assertNotDispatched(AbilityUpdated::class);
});
