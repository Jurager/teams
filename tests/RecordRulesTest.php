<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Jurager\Teams\Models\Ability;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\OwnedArticle;
use Jurager\Teams\Tests\Fixtures\User;

afterEach(fn () => Relation::morphMap([], false));

it('lists the permissions and recipients of a records explicit rules', function () {
    $team = team();
    $user = member($team);
    $member = $team->member($user);
    $role = $member->role();
    $group = $team->groups()->create(['code' => 'editors'])->add($user);
    $article = article($team);
    $member->allow('articles.edit')->on($article);
    $role->forbid('articles.edit')->on($article);
    $group->allow('articles.edit')->on($article);
    DB::enableQueryLog();

    $rules = $team->rules($article)->get();

    expect($rules)->toHaveCount(3)->and(DB::getQueryLog())->toHaveCount(5);
    DB::flushQueryLog();

    foreach ([$user, $role, $group] as $recipient) {
        $rule = $rules->first(fn ($rule) => $rule->recipient->is($recipient));

        expect($rule)->toBeInstanceOf(Ability::class)
            ->and($rule->permission->code)->toBe('articles.edit')
            ->and($rule->forbidden)->toBe($recipient->is($role));
    }

    expect(DB::getQueryLog())->toBeEmpty();
});

it('scopes record rules by team, record and morph type', function () {
    Relation::morphMap(['article' => Article::class, 'owned-article' => OwnedArticle::class]);
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.edit')->on($article);
    $role->forbid('articles.view')->on(article($team));
    $role->forbid('articles.delete')->on(OwnedArticle::findOrFail($article->id));
    $other = team();
    role($other, 'other')->allow('articles.edit')->on($article);

    expect($team->rules($article)->get()->pluck('permission.code')->all())->toBe(['articles.edit'])
        ->and($team->rules(new Article)->get())->toBeEmpty();
});

it('removes forgotten rules and supports filtering and pagination', function () {
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.view')->on($article);
    $role->allow('articles.edit')->on($article);
    $role->forget('articles.edit')->on($article);

    $page = $team->rules($article)->whereRelation('permission', 'code', 'articles.view')->paginate(10);

    expect($team->abilities()->count())->toBe(1)
        ->and($page->total())->toBe(1)
        ->and($page->first()->permission->code)->toBe('articles.view');
});

it('resolves recipient aliases from the morph map', function () {
    Relation::morphMap(['user' => User::class, 'role' => Role::class, 'group' => Group::class]);
    $team = team();
    $user = member($team);
    $article = article($team);
    $team->member($user)->allow('articles.edit')->on($article);
    $team->member($user)->role()->forbid('articles.edit')->on($article);
    $group = $team->groups()->create(['code' => 'editors']);
    $group->allow('articles.edit')->on($article);
    $rules = $team->rules($article)->get()->keyBy('recipient_type');

    expect($rules->keys()->all())->toEqualCanonicalizing(['user', 'role', 'group'])
        ->and($rules['user']->recipient->is($user))->toBeTrue()
        ->and($rules['role']->recipient)->toBeInstanceOf(Role::class)
        ->and($rules['group']->recipient->is($group))->toBeTrue();
});
