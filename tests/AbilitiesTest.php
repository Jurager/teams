<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Models\Ability;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\CustomAbility;
use Jurager\Teams\Tests\Fixtures\OwnedArticle;
use Jurager\Teams\Tests\Fixtures\User;

class SoftRecordAbility extends Ability
{
    use SoftDeletes;
}

afterEach(fn () => Relation::morphMap([], false));

it('batches record and recipient filters by morph type and keeps surrounding constraints', function () {
    Relation::morphMap([
        'article' => Article::class, 'owned-article' => OwnedArticle::class,
        'user' => User::class, 'role' => Role::class, 'group' => Group::class,
    ]);
    $team = team();
    $member = $team->member(member($team));
    $role = $member->role();
    $group = $team->groups()->create(['code' => 'editors']);
    $article = article($team);
    $owned = OwnedArticle::findOrFail($article->id);
    $other = article($team);
    $member->allow('articles.edit')->on($article);
    $role->forbid('articles.edit')->on($owned);
    $group->allow('articles.edit')->on($other);
    role(team(), 'other')->allow('articles.edit')->on($article);

    $records = (function () use ($article, $owned) {
        yield $article;
        yield $owned;
    })();
    $rules = $team->abilities()->forRecords($records)->forRecipients([$member->user, $role])->get();

    expect($rules)->toHaveCount(2)
        ->and($rules->pluck('entity_type')->all())->toEqualCanonicalizing(['article', 'owned-article'])
        ->and($team->abilities()->forRecords([$article, $owned])->forRecipients([$group])->count())->toBe(0)
        ->and($team->abilities()->forRecords([])->count())->toBe(0)
        ->and($team->abilities()->forRecipients([])->count())->toBe(0)
        ->and($team->abilities()->forRecipients([$role])->where('forbidden', false)->count())->toBe(0);
});

it('keeps a loaded snapshot until explicitly reloaded and ignores malformed records', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on($article);
    $member->load([null, 'invalid', $article, article(team())]);
    $member->forbid('articles.edit')->on($article);
    DB::enableQueryLog();

    expect($member->can('articles.edit', $article))->toBeTrue()
        ->and(DB::getQueryLog())->toBeEmpty();

    $member->load([$article]);
    expect($member->inspect('articles.edit', $article)->code())->toBe('user_forbidden');
});

it('honors the configured ability models global scopes during live and preloaded checks', function (bool $preload) {
    config(['teams.models.ability' => CustomAbility::class]);
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on($article);
    CustomAbility::addGlobalScope('hidden', fn (Builder $query) => $query->whereRaw('1 = 0'));

    if ($preload) {
        $member->load([$article]);
    }

    expect($member->can('articles.edit', $article))->toBeFalse();
})->with([false, true]);

it('rolls back writes and events if a listener changes a rules identity', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    Event::fake(AbilityUpdated::class);
    Ability::saving(fn (Ability $ability) => $ability->recipient_id = 999);

    expect(fn () => $member->allow('articles.edit')->on($article))->toThrow(RuntimeException::class)
        ->and($team->abilities()->count())->toBe(0)
        ->and($team->permissions()->count())->toBe(0);
    Event::assertNotDispatched(AbilityUpdated::class);
});

it('physically removes forgotten rules even for a custom ability with soft deletes', function () {
    Schema::table(config('teams.tables.abilities'), fn (Blueprint $table) => $table->softDeletes());
    config(['teams.models.ability' => SoftRecordAbility::class]);
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on($article);
    $member->forget('articles.edit')->on($article);

    expect(SoftRecordAbility::withTrashed()->count())->toBe(0);

    $member->forbid('articles.edit')->on($article);
    expect($member->inspect('articles.edit', $article)->code())->toBe('user_forbidden');
});
