<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\OwnedArticle;

afterEach(fn () => Relation::morphMap([], false));

it('keeps rule precedence', function (array $rolePermissions, array $groupPermissions, array $rules, bool $global, bool $expected, bool $preload) {
    $team = team();
    $user = member($team, $rolePermissions);
    $member = $team->member($user);
    $group = $team->groups()->create(['code' => 'editors'])->grant($groupPermissions)->add($user);
    $article = article($team);
    $targets = ['role' => $member->role(), 'group' => $group, 'user' => $member];

    foreach ($rules as [$target, $forbidden, $permission]) {
        $targets[$target]->{$forbidden ? 'forbid' : 'allow'}($permission)->on($article);
    }

    if ($global) {
        Group::create(['code' => 'support'])->grant('articles.*')->add($user);
    }

    expect(($preload ? $member->load([$article]) : $member)->can('articles.edit', $article))->toBe($expected);
})->with([
    'no grant' => [[], [], [], false, false],
    'role permission' => [['articles.*'], [], [], false, true],
    'role rule' => [[], [], [['role', false, 'articles.edit']], false, true],
    'role deny overrides role permission' => [['articles.*'], [], [['role', true, 'articles.edit']], false, false],
    'group permission overrides role deny' => [[], ['articles.*'], [['role', true, 'articles.edit']], false, true],
    'group rule overrides role deny' => [[], [], [['role', true, 'articles.edit'], ['group', false, 'articles.edit']], false, true],
    'group deny overrides role grant' => [[], [], [['role', false, 'articles.edit'], ['group', true, 'articles.edit']], false, false],
    'user grant overrides group deny' => [[], [], [['group', true, 'articles.edit'], ['user', false, 'articles.edit']], false, true],
    'user deny overrides wildcard user grant' => [[], [], [['user', false, 'articles.*'], ['user', true, 'articles.edit']], false, false],
    'global grant overrides user deny' => [[], [], [['user', true, 'articles.edit']], true, true],
])->with([
    'queried' => false,
    'preloaded' => true,
]);

it('decides on a personal grant before loading lower levels', function () {
    $team = team();
    $user = member($team, ['articles.*']);
    $member = $team->member($user);
    $group = $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    $article = article($team);
    $member->role()->forbid('articles.edit')->on($article);
    $group->forbid('articles.edit')->on($article);
    $member->allow('articles.edit')->on($article);
    DB::enableQueryLog();

    expect($member->can('articles.edit', $article))->toBeTrue()
        ->and(DB::getQueryLog())->toHaveCount(4);
});

it('does not grow record checks with the number of groups', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    $article = article($team);

    foreach (range(1, 5) as $i) {
        $team->groups()->create(['code' => 'group-'.$i])->grant('articles.view')->add($user);
    }

    DB::enableQueryLog();

    expect($team->member($user)->can('articles.edit', $article))->toBeTrue()
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual(7);
});

it('applies a personal rule only to team members', function () {
    $team = team();
    $user = user();
    $article = article($team);
    $team->member($user)->allow('articles.edit')->on($article);

    expect($team->member($user)->can('articles.edit', $article))->toBeFalse();

    role($team, 'reader');
    $team->add($user)->as('reader');

    expect($team->member($user)->can('articles.edit', $article))->toBeTrue();
});

it('forgets a rule without creating records', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);

    $member->forget('missing.permission')->on($article);

    expect($team->permissions()->count())->toBe(0)
        ->and($team->abilities()->count())->toBe(0);

    $member->allow('articles.edit')->on($article);
    expect($member->can('articles.edit', $article))->toBeTrue();

    $member->forget('articles.edit')->on($article);
    expect($member->can('articles.edit', $article))->toBeFalse()
        ->and($member->user->abilities()->count())->toBe(0);
});

it('shares rules between records given by class and key and morph aliases', function () {
    Relation::morphMap(['article' => Article::class]);
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);

    $member->allow('articles.edit')->on(Article::class, $article->id);
    expect($member->can('articles.edit', $article))->toBeTrue();

    $member->forbid('articles.edit')->on($article);
    expect($member->can('articles.edit', $article))->toBeFalse()
        ->and($team->abilities()->count())->toBe(1)
        ->and($member->user->abilities()->count())->toBe(1)
        ->and($team->abilities()->first()->entity->is($article))->toBeTrue();

    $member->forget('articles.edit')->on(Article::class, $article->id);
    expect($member->user->abilities()->count())->toBe(0);
});

it('needs a saved record or a model class and key', function (array $record) {
    $member = team()->member(user());

    expect(fn () => $member->allow('articles.edit')->on(...$record))->toThrow(InvalidArgumentException::class);
})->with([
    'unsaved record' => fn () => [new Article],
    'non-model class' => [[stdClass::class, 1]],
    'class without key' => [[Article::class]],
]);

it('does not let global groups have record rules', function () {
    Group::create(['code' => 'support'])->allow('articles.edit');
})->throws(LogicException::class);

it('denies records of another team even to the owner', function () {
    $team = team();
    $owner = $team->member($team->owner);

    expect($owner->can('articles.edit', article(team())))->toBeFalse()
        ->and($owner->can('articles.edit', article($team)))->toBeTrue();
});

it('lets record owners pass without team membership', function () {
    $team = team();
    $owner = user();
    $article = OwnedArticle::create(['team_id' => $team->id, 'owner_id' => $owner->id]);

    expect($owner->in($team))->toBeFalse()
        ->and($team->member($owner)->can('articles.edit', $article))->toBeTrue()
        ->and($team->member(user())->can('articles.edit', $article))->toBeFalse();
});

it('keeps an occupied role with its rules', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    $role = $team->member($user)->role();
    $article = article($team);
    $role->forbid('articles.edit')->on($article);

    expect(fn () => $role->delete())->toThrow(RuntimeException::class, 'Reassign or remove team members')
        ->and($role->fresh())->not->toBeNull()
        ->and($role->permissions()->count())->toBe(1)
        ->and($role->abilities()->count())->toBe(1)
        ->and($team->member($user)->can('articles.edit', $article))->toBeFalse();
});

it('rolls back role cleanup when a deleting listener fails', function () {
    $role = role(team(), 'unused', ['articles.edit']);
    Event::listen('eloquent.deleting: '.Role::class, fn () => throw new RuntimeException('Listener failed.'));

    expect(fn () => $role->delete())->toThrow(RuntimeException::class, 'Listener failed.')
        ->and($role->fresh())->not->toBeNull()
        ->and($role->permissions()->count())->toBe(1);
});

it('preserves assignments when role deletion is cancelled', function () {
    $team = team();
    $role = role($team, 'unused', ['articles.edit']);
    $role->forbid('articles.edit')->on(article($team));
    Role::deleting(fn () => false);

    expect($role->delete())->toBeFalse()
        ->and($role->fresh())->not->toBeNull()
        ->and($role->permissions()->count())->toBe(1)
        ->and($role->abilities()->count())->toBe(1);
});

it('preserves forbids and permissions when group deletion is cancelled', function () {
    $team = team();
    $user = member($team, ['articles.*']);
    $article = article($team);
    $group = $team->groups()->create(['code' => 'restricted'])->grant('articles.view')->add($user);
    $group->forbid('articles.edit')->on($article);
    Group::deleting(fn () => false);

    expect($team->member($user)->can('articles.edit', $article))->toBeFalse()
        ->and($group->delete())->toBeFalse()
        ->and($group->fresh())->not->toBeNull()
        ->and($group->permissions()->count())->toBe(1)
        ->and($group->abilities()->count())->toBe(1)
        ->and($group->members()->whereKey($user)->exists())->toBeTrue()
        ->and($team->member($user)->can('articles.edit', $article))->toBeFalse();
});

it('denies records with an unselected or null team key before checking grants', function (string $source, bool $preload) {
    $team = team();
    $user = $source === 'team owner' ? $team->owner : member($team, ['articles.*']);
    $member = $team->member($user);

    if ($source === 'global group') {
        Group::create(['code' => 'support'])->grant('articles.*')->add($user);
    }

    $record = OwnedArticle::create([
        'team_id' => $team->id,
        'owner_id' => $source === 'record owner' ? $user->id : null,
    ]);
    $partial = OwnedArticle::select('id', 'owner_id')->findOrFail($record->id);
    $foreign = article(team());
    $foreignPartial = Article::select('id')->findOrFail($foreign->id);
    $unassigned = $record->replicate()->forceFill(['team_id' => null]);

    if ($preload) {
        $member->load([$record, $partial, $foreign, $foreignPartial, $unassigned]);
    }

    expect($member->can('articles.edit', $record))->toBeTrue();

    foreach ([$partial, $foreign, $foreignPartial, $unassigned] as $record) {
        expect($member->can('articles.edit', $record))->toBeFalse()
            ->and($member->canAny(['articles.edit'], $record))->toBeFalse()
            ->and($member->inspect('articles.edit', $record)->code())->toBe('team_mismatch');
    }
})->with(['role', 'team owner', 'record owner', 'global group'])->with([false, true]);

it('removes the permissions and rules of a deleted group', function () {
    $team = team();
    $user = member($team);
    $group = $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    $group->allow('articles.publish')->on(article($team));

    $group->delete();

    expect($team->member($user)->can('articles.edit'))->toBeFalse();

    $this->assertDatabaseMissing(config('teams.tables.entity_permission'), [
        'entity_type' => $group->getMorphClass(), 'entity_id' => $group->getKey(),
    ]);
    $this->assertDatabaseMissing(config('teams.tables.abilities'), [
        'recipient_type' => $group->getMorphClass(), 'recipient_id' => $group->getKey(),
    ]);
});

it('checks preloaded records without queries', function () {
    $team = team();
    $user = member($team, ['articles.view']);
    $member = $team->member($user);
    $group = $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($user);
    [$first, $second, $third] = [article($team), article($team), OwnedArticle::create(['team_id' => $team->id, 'owner_id' => $user->id])];
    $member->forbid('articles.edit')->on($first);
    $group->forbid('articles.view')->on($second);
    $member->role()->allow('articles.delete')->on($second);
    $records = [$first, $second, $third];
    $checks = ['articles.view', 'articles.edit', 'articles.delete'];
    $expected = collect($records)->map(fn ($record) => collect($checks)->map(fn ($check) => $member->can($check, $record))->all())->all();

    $member->load([...$records, article(team())]);
    DB::enableQueryLog();

    expect(collect($records)->map(fn ($record) => collect($checks)->map(fn ($check) => $member->can($check, $record))->all())->all())
        ->toBe($expected)
        ->toBe([[true, false, false], [false, true, true], [true, true, true]])
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('queries records that were not preloaded', function () {
    $team = team();
    $member = $team->member(member($team, ['articles.*']));
    $article = article($team);
    $member->load([article($team)]);
    $member->forbid('articles.edit')->on($article);

    expect($member->can('articles.edit', $article))->toBeFalse()
        ->and($member->can('articles.view', $article))->toBeTrue();
});
