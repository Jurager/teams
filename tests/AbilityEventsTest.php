<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\AbilityUpdated;

class AbilityAuditListener implements ShouldQueue
{
    public static array $events = [];

    public function handle(AbilityUpdated $event): void
    {
        self::$events[] = $event;
    }
}

it('delivers a serialized removal event after its ability and team have been deleted', function () {
    config(['queue.default' => 'sync']);
    AbilityAuditListener::$events = [];
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.edit')->until(now()->addHour())->on($article);
    $ability = $team->abilities()->firstOrFail();
    Event::listen(AbilityUpdated::class, AbilityAuditListener::class);

    DB::transaction(function () use ($role, $article, $team) {
        $role->forget('articles.edit')->on($article);
        $team->purge();
        expect(AbilityAuditListener::$events)->toBeEmpty();
    });

    $events = AbilityAuditListener::$events;
    expect($events)->toHaveCount(1)
        ->and($events[0]->abilityId)->toBe($ability->id)
        ->and($events[0]->teamId)->toBe($team->id)
        ->and($events[0]->permissionId)->toBe($ability->permission_id)
        ->and($events[0]->permission)->toBe('articles.edit')
        ->and($events[0]->recipientType)->toBe($role->getMorphClass())
        ->and($events[0]->recipientId)->toBe($role->id)
        ->and($events[0]->entityType)->toBe($article->getMorphClass())
        ->and($events[0]->entityId)->toBe($article->id)
        ->and($events[0]->previous)->toBeFalse()
        ->and($events[0]->forbidden)->toBeNull()
        ->and($events[0]->previousExpiresAt?->isImmutable())->toBeTrue()
        ->and($ability->fresh())->toBeNull();
});

it('retains each changes values when several changes commit together', function () {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $deadline = now()->addHour()->startOfSecond();
    $events = [];
    Event::listen(AbilityUpdated::class, function ($event) use (&$events) {
        $events[] = $event;
    });

    DB::transaction(function () use ($member, $article, $deadline, &$events) {
        $member->allow('articles.edit')->until($deadline)->on($article);
        $member->forbid('articles.edit')->on($article);
        $member->forget('articles.edit')->on($article);
        expect($events)->toBeEmpty();
    });

    expect(array_map(fn ($event) => [$event->previous, $event->forbidden], $events))->toBe([
        [null, false], [false, true], [true, null],
    ])->and($events[0]->expiresAt->equalTo($deadline))->toBeTrue()
        ->and($events[1]->previousExpiresAt->equalTo($deadline))->toBeTrue()
        ->and($events[2]->expiresAt)->toBeNull();

    $deadline->addDay();
    expect($events[0]->expiresAt->equalTo($deadline))->toBeFalse();
});
