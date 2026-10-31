<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\OwnedArticle;
use Jurager\Teams\Tests\Fixtures\User;

enum ExternalPermission: string
{
    case Update = 'products.update';
    case View = 'products.view';
}

afterEach(function () {
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
});

it('allows forbids and forgets external records for every recipient', function (string $recipient) {
    $team = team();
    $member = $team->member(member($team));
    $target = match ($recipient) {
        'user' => $member,
        'role' => $member->role(),
        'group' => $team->groups()->create(['code' => 'editors'])->add($member->user),
    };

    $target->allow(ExternalPermission::Update)->on('catalog.product', 42);
    expect($member->can(ExternalPermission::Update, 'catalog.product', '42'))->toBeTrue()
        ->and($member->inspect(ExternalPermission::Update, 'catalog.product', 42)->code())->toBe("{$recipient}_allowed")
        ->and($member->can([ExternalPermission::View, ExternalPermission::Update], 'catalog.product', 42))->toBeFalse()
        ->and($member->canAny([ExternalPermission::View, ExternalPermission::Update], 'catalog.product', 42))->toBeTrue();

    $target->forbid('products.update')->on('catalog.product', '42');
    expect($member->inspect('products.update', 'catalog.product', 42)->code())->toBe("{$recipient}_forbidden")
        ->and($team->rules('catalog.product', 42)->count())->toBe(1);

    $target->forget('products.update')->on('catalog.product', 42);
    expect($member->can('products.update', 'catalog.product', 42))->toBeFalse()
        ->and($team->rules('catalog.product', 42)->get())->toBeEmpty();
})->with(['user', 'role', 'group']);

it('isolates external rules by team type key and permission', function () {
    $team = team();
    $user = member($team);
    $member = $team->member($user);
    $other = team();
    $other->add($user)->as(role($other, 'reader'));
    $article = article($team);
    $member->allow('products.update')->on('products', $article->id);

    expect($member->can('products.update', 'products', $article->id))->toBeTrue()
        ->and($member->can('products.update', 'products', $article->id + 1))->toBeFalse()
        ->and($member->can('products.update', 'warehouse.products', $article->id))->toBeFalse()
        ->and($member->can('products.delete', 'products', $article->id))->toBeFalse()
        ->and($member->can('products.update', $article))->toBeFalse()
        ->and($other->member($user)->can('products.update', 'products', $article->id))->toBeFalse()
        ->and($other->rules('products', $article->id)->get())->toBeEmpty();
});

it('keeps personal group and role precedence and wildcard forbids for external records', function () {
    $team = team();
    $member = $team->member(member($team));
    $group = $team->groups()->create(['code' => 'editors'])->add($member->user);
    $member->role()->forbid('products.*')->on('products', 42);
    $group->allow('products.update')->on('products', 42);
    expect($member->inspect('products.update', 'products', 42)->code())->toBe('group_allowed');

    $group->forbid('products.*')->on('products', 42);
    expect($member->inspect('products.update', 'products', 42)->code())->toBe('group_forbidden');

    $member->allow('products.update')->on('products', 42);
    expect($member->inspect('products.update', 'products', 42)->code())->toBe('user_allowed');

    $member->forbid('products.*')->on('products', 42);
    expect($member->inspect('products.update', 'products', 42)->code())->toBe('user_forbidden');
});

it('falls through expired external rules for every recipient', function (string $recipient, bool $forbidden) {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team, $forbidden ? ['products.*'] : []));
    $target = match ($recipient) {
        'user' => $member,
        'role' => $member->role(),
        'group' => $team->groups()->create(['code' => 'editors'])->add($member->user),
    };
    $deadline = now()->addMinute()->startOfSecond();
    $target->{$forbidden ? 'forbid' : 'allow'}('products.update')->until($deadline)->on('products', 42);

    expect($member->can('products.update', 'products', 42))->toBe(! $forbidden);
    $this->travelTo($deadline);
    expect($member->can('products.update', 'products', 42))->toBe($forbidden);
})->with(['user', 'role', 'group'])->with([false, true]);

it('keeps owner global group and membership rules for external checks', function () {
    $team = team();
    $stranger = user();
    $context = $team->member($stranger);
    $context->allow('products.update')->on('products', 42);

    expect($team->member($team->owner)->inspect('products.update', 'products', 42)->code())->toBe('team_owner')
        ->and($context->inspect('products.update', 'products', 42)->code())->toBe('not_member');

    Group::create(['code' => 'support'])->grant('products.*')->add($stranger);
    expect($context->inspect('products.update', 'products', 42)->code())->toBe('global_group');
});

it('does not query or instantiate a local model for an external type when morph maps are enforced', function () {
    Relation::enforceMorphMap(['user' => User::class, 'role' => Role::class, 'group' => Group::class, 'article' => Article::class]);
    $team = team();
    $member = $team->member(member($team));
    DB::enableQueryLog();
    $member->allow('products.update')->on('missing_service.products', 42);
    $rule = $team->rules('missing_service.products', 42)->firstOrFail();

    expect($member->can('products.update', 'missing_service.products', 42))->toBeTrue()
        ->and($rule->entity_type)->toBe('missing_service.products')
        ->and($rule->entity_id)->toBe(42)
        ->and($rule->permission->code)->toBe('products.update')
        ->and($rule->recipient->is($member->user))->toBeTrue()
        ->and(collect(DB::getQueryLog())->pluck('query')->contains(fn ($query) => str_contains($query, 'from "missing_service.products"')))->toBeFalse();
});

it('does not let model class names or morph aliases bypass team validation', function () {
    Relation::morphMap(['article' => Article::class]);
    $team = team();
    $owner = $team->member($team->owner);
    $foreign = article(team());
    $owner->allow('articles.edit')->on(Article::class, $foreign->id);

    foreach ([Article::class, 'article'] as $type) {
        expect($owner->inspect('articles.edit', $type, $foreign->id)->code())->toBe('team_mismatch')
            ->and($owner->canAny(['articles.edit'], $type, $foreign->id))->toBeFalse();
    }

    expect($owner->inspect('articles.edit', $foreign)->code())->toBe('team_mismatch');
});

it('queries and updates model rules through a mapped type without changing model checks', function () {
    Relation::morphMap(['article' => Article::class]);
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on('article', $article->id);
    $member->forbid('articles.edit')->on(Article::class, $article->id);

    expect($team->rules('article', $article->id)->count())->toBe(1)
        ->and($member->inspect('articles.edit', $article)->code())->toBe('user_forbidden')
        ->and($team->rules($article)->first()->entity->is($article))->toBeTrue();
});

it('keeps external changes live when only model records were preloaded', function () {
    $team = team();
    $member = $team->member(member($team));
    $member->load([article($team)]);
    $member->allow('products.update')->on('products', 42);
    expect($member->can('products.update', 'products', 42))->toBeTrue();
    $member->forbid('products.update')->on('products', 42);
    expect($member->can('products.update', 'products', 42))->toBeFalse();
});

it('reports external rule changes after commit without no-op events', function () {
    $this->freezeTime();
    $team = team();
    $member = $team->member(member($team));
    $deadline = now()->addHour()->startOfSecond();
    Event::fake(AbilityUpdated::class);
    DB::beginTransaction();
    $member->forget('missing')->on('products', 42);
    $member->allow('products.update')->until($deadline)->on('products', 42);
    $member->allow('products.update')->until($deadline)->on('products', 42);
    $member->forbid('products.update')->until($deadline)->on('products', 42);
    $member->forget('products.update')->on('products', 42);
    $member->forget('products.update')->on('products', 42);
    Event::assertNotDispatched(AbilityUpdated::class);
    DB::commit();

    Event::assertDispatchedTimes(AbilityUpdated::class, 3);
    $event = unserialize(serialize(Event::dispatched(AbilityUpdated::class)->last()[0]));
    expect($event->entityType)->toBe('products')
        ->and($event->entityId)->toBe(42)
        ->and($event->teamId)->toBe($team->id)
        ->and($event->previous)->toBeTrue()
        ->and($event->forbidden)->toBeNull()
        ->and($event->previousExpiresAt->equalTo($deadline))->toBeTrue()
        ->and($team->abilities()->count())->toBe(0)
        ->and($team->permissions()->where('code', 'missing')->exists())->toBeFalse();
});

it('rolls back external assignments and their events with the outer transaction', function () {
    $team = team();
    $member = $team->member(member($team));
    Event::fake(AbilityUpdated::class);
    DB::beginTransaction();
    $member->allow('products.update')->on('products', 42);
    DB::rollBack();

    expect($team->abilities()->count())->toBe(0)
        ->and($team->permissions()->count())->toBe(0);
    Event::assertNotDispatched(AbilityUpdated::class);
});

it('rejects invalid external identities for writes checks and listings', function (string $type, int|string|null $key) {
    $team = team();
    $member = $team->member(member($team, ['products.*']));

    expect(fn () => $member->allow('products.update')->on($type, $key))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $member->can('products.update', $type, $key))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $member->canAny(['products.update'], $type, $key))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $member->inspect('products.update', $type, $key))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $team->rules($type, $key)->get())->toThrow(InvalidArgumentException::class)
        ->and($team->abilities()->count())->toBe(0);
})->with([
    'no key' => ['products', null],
    'empty type' => ['', 42],
    'blank type' => [' ', 42],
    'padded type' => ['products ', 42],
    'long type' => [str_repeat('x', 256), 42],
    'non-model class' => [stdClass::class, 42],
    'non-numeric key' => ['products', 'abc-123'],
    'empty key' => ['products', ''],
    'negative key' => ['products', -1],
    'overflowing key' => ['products', '999999999999999999999999999'],
]);

it('requires a type for a record key instead of falling back to team permissions', function () {
    $team = team();
    $member = $team->member($team->owner);
    expect(fn () => $member->can('products.update', null, 42))->toThrow(InvalidArgumentException::class);
});

it('accepts zero and numeric string keys as external record identities', function (int|string $key) {
    $team = team();
    $member = $team->member(member($team));
    $member->allow('products.update')->on('products', $key);
    expect($member->can('products.update', 'products', $key))->toBeTrue()
        ->and($team->rules('products', $key)->count())->toBe(1);
})->with([0, '0', 42, '42', '00042', PHP_INT_MAX]);

it('preserves concrete model class writes even if that class name is also a morph alias', function () {
    Relation::morphMap([Article::class => OwnedArticle::class, 'article' => Article::class]);
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on(Article::class, $article->id);

    expect($member->can('articles.edit', $article))->toBeTrue()
        ->and($team->rules($article)->firstOrFail()->entity_type)->toBe('article');
});

it('keeps external checks out of automatic Gate team inference', function () {
    $team = team();
    $member = $team->member(member($team));
    $member->allow('products.update')->on('products', 42);

    expect($member->can('products.update', 'products', 42))->toBeTrue()
        ->and($member->user->can('products.update', ['products', 42]))->toBeFalse();
});

it('rejects case variants of model morph aliases as external types', function () {
    Relation::morphMap(['article' => Article::class]);
    $team = team();
    $owner = $team->member($team->owner);

    expect(fn () => $owner->allow('articles.edit')->on('ARTICLE', 42))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $owner->can('articles.edit', 'ARTICLE', 42))->toThrow(InvalidArgumentException::class)
        ->and($team->abilities()->count())->toBe(0);
});
