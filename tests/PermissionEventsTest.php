<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Events\GroupMembersUpdated;
use Jurager\Teams\Events\PermissionsUpdated;

it('reports actual permission assignment changes', function (string $relation) {
    $team = team();
    $target = $team->{$relation}()->create(['code' => 'editors']);
    Event::fake([PermissionsUpdated::class]);

    $target->grant('articles.view', 'articles.edit');
    $ids = $target->permissions->pluck('id', 'code');
    $target->grant('articles.view');
    $target->revoke('missing');
    $target->sync('articles.edit', 'articles.publish');
    $publish = $target->permissions->firstWhere('code', 'articles.publish')->getKey();
    $target->revoke('articles.edit');
    $target->sync();
    $target->sync();

    Event::assertDispatchedTimes(PermissionsUpdated::class, 4);
    $events = Event::dispatched(PermissionsUpdated::class)->map(fn ($arguments) => $arguments[0]);

    expect($events->map(fn ($event) => [collect($event->attached)->sort()->values()->all(), $event->detached])->all())->toBe([
        [collect([$ids['articles.view'], $ids['articles.edit']])->sort()->values()->all(), []],
        [[$publish], [$ids['articles.view']]],
        [[], [$ids['articles.edit']]],
        [[], [$publish]],
    ])->and($events->every(fn ($event) => $event->model->is($target)))->toBeTrue();
})->with(['roles', 'groups']);

it('reports only eligible members who really joined or left a group', function () {
    $team = team();
    $user = member($team);
    $stranger = user();
    $group = $team->groups()->create(['code' => 'editors']);
    Event::fake([GroupMembersUpdated::class]);

    $group->add(new Collection([$user, $stranger]));
    $group->add($user);
    $group->remove($stranger);
    $group->remove($user);
    $group->remove($user);

    Event::assertDispatchedTimes(GroupMembersUpdated::class, 2);
    Event::assertDispatched(GroupMembersUpdated::class, fn ($event) => $event->group->is($group)
        && $event->attached === [$user->id] && $event->detached === []);
    Event::assertDispatched(GroupMembersUpdated::class, fn ($event) => $event->attached === [] && $event->detached === [$user->id]);
});

it('reports rule creation, reversal and removal without no-op events', function (string $recipient) {
    $team = team();
    $user = member($team);
    $member = $team->member($user);
    $article = article($team);
    $target = match ($recipient) {
        'user' => $member,
        'role' => $member->role(),
        'group' => $team->groups()->create(['code' => 'editors']),
    };
    $model = $recipient === 'user' ? $user : $target;
    Event::fake([AbilityUpdated::class]);

    $target->forget('missing')->on($article);
    $target->allow('articles.edit')->on($article);
    $target->allow('articles.edit')->on($article);
    $target->forbid('articles.edit')->on($article);
    $target->forbid('articles.edit')->on($article);
    $target->forget('articles.edit')->on($article);
    $target->forget('articles.edit')->on($article);

    Event::assertDispatchedTimes(AbilityUpdated::class, 3);
    Event::assertDispatched(AbilityUpdated::class, fn ($event) => $event->recipientId === $model->getKey() && $event->recipientType === $model->getMorphClass()
        && $event->entityId === $article->getKey() && $event->entityType === $article->getMorphClass() && $event->previous === null && $event->forbidden === false);
    Event::assertDispatched(AbilityUpdated::class, fn ($event) => $event->previous === false && $event->forbidden === true);
    Event::assertDispatched(AbilityUpdated::class, fn ($event) => $event->previous === true && $event->forbidden === null);
})->with(['user', 'role', 'group']);

dataset('access changes', [
    'permissions granted' => [PermissionsUpdated::class, fn ($role) => $role->grant('articles.edit')],
    'permissions revoked' => [PermissionsUpdated::class, fn ($role) => $role->revoke('articles.view')],
    'permissions synced' => [PermissionsUpdated::class, fn ($role) => $role->sync('articles.edit')],
    'group joined' => [GroupMembersUpdated::class, fn ($role, $group, $user) => $group->add($user)],
    'group left' => [GroupMembersUpdated::class, fn ($role, $group, $user) => $group->remove($user), true],
    'rule assigned' => [AbilityUpdated::class, fn ($role, $group, $user, $article) => $role->allow('articles.edit')->on($article)],
    'rule removed' => [AbilityUpdated::class, fn ($role, $group, $user, $article) => $role->forget('articles.view')->on($article)],
]);

it('dispatches access changes only after the outermost commit', function (string $event, Closure $change, bool $joined = false) {
    $team = team();
    $user = member($team);
    $role = $team->member($user)->role()->grant('articles.view');
    $group = $team->groups()->create(['code' => 'editors']);
    $article = article($team);
    $role->allow('articles.view')->on($article);

    if ($joined) {
        $group->add($user);
    }

    $events = [];
    Event::listen($event, function ($event) use (&$events) {
        $events[] = [$event, DB::transactionLevel()];
    });

    DB::transaction(function () use ($change, $role, $group, $user, $article, &$events) {
        $change($role, $group, $user, $article);
        expect($events)->toBeEmpty();
    });

    expect($events)->toHaveCount(1)
        ->and($events[0][1])->toBe(0);
})->with('access changes');

it('discards access events and changes when the transaction rolls back', function (string $event, Closure $change, bool $joined = false) {
    $team = team();
    $user = member($team);
    $role = $team->member($user)->role()->grant('articles.view');
    $group = $team->groups()->create(['code' => 'editors']);
    $article = article($team);
    $role->allow('articles.view')->on($article);

    if ($joined) {
        $group->add($user);
    }

    $events = [];
    Event::listen($event, function ($event) use (&$events) {
        $events[] = $event;
    });

    DB::beginTransaction();
    $change($role, $group, $user, $article);
    DB::rollBack();

    expect($events)->toBeEmpty()
        ->and($role->fresh()->permissions->pluck('code')->all())->toBe(['articles.view'])
        ->and($role->fresh()->abilities->pluck('permission_id')->all())->toHaveCount(1)
        ->and($group->members()->whereKey($user)->exists())->toBe($joined);
})->with('access changes');
