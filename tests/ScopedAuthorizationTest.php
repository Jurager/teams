<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Jurager\Teams\Facades\Teams;
use Jurager\Teams\Scope;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\SanctumServiceProvider;

enum ScopeCode: string
{
    case Edit = 'articles.edit';
}

class ScopedTokenUser extends User
{
    use HasApiTokens;

    protected $table = 'users';
}

it('limits the user to the permissions of the scope', function () {
    $team = team();
    $user = member($team, ['articles.edit', 'articles.delete']);
    $article = article($team);

    Teams::scope(fn () => Scope::to('articles.edit'));

    expect($user->can('articles.edit', $article))->toBeTrue()
        ->and($user->can('articles.delete', $article))->toBeFalse()
        ->and($user->can(['articles.edit', 'articles.delete'], $article))->toBeFalse()
        ->and($user->canAny(['articles.edit', 'articles.delete'], $article))->toBeTrue();
});

it('never grants what the user does not have', function () {
    $team = team();
    $user = member($team, ['articles.view']);

    Teams::scope(fn () => Scope::to('articles.view', 'articles.edit'));

    expect($user->can('articles.view', $team))->toBeTrue()
        ->and($user->can('articles.edit', $team))->toBeFalse();

    $team->member($user)->forbid('articles.view')->on($article = article($team));

    expect($user->can('articles.view', $article))->toBeFalse();
});

it('accepts lists, enums and wildcards', function () {
    $team = team();
    $user = member($team, ['articles.*', 'comments.view']);

    Teams::scope(fn () => Scope::to([ScopeCode::Edit], 'comments.*'));

    expect($user->can('articles.edit', $team))->toBeTrue()
        ->and($user->can('articles.view', $team))->toBeFalse()
        ->and($user->can('comments.view', $team))->toBeTrue();

    Teams::scope(fn () => Scope::to('articles.*'));

    expect($user->can('articles.view', $team))->toBeTrue()
        ->and($user->can('comments.view', $team))->toBeFalse();
});

it('limits the user to one team', function () {
    $team = team();
    $other = team();
    $user = member($team, ['articles.edit']);
    $other->add($user)->as(role($other, 'editor', ['articles.edit']));

    Teams::scope(fn () => Scope::to('articles.edit')->in($team));

    expect($user->can('articles.edit', $team))->toBeTrue()
        ->and($user->can('articles.edit', article($team)))->toBeTrue()
        ->and($user->can('articles.edit', $other))->toBeFalse()
        ->and($user->can('articles.edit', article($other)))->toBeFalse()
        ->and($user->can('articles.edit'))->toBeFalse();

    Teams::scope(fn () => Scope::to('articles.edit')->in((string) $team->getKey()));

    expect($user->can('articles.edit', article($team)))->toBeTrue()
        ->and($user->can('articles.edit', article($other)))->toBeFalse();

    Teams::scope(fn () => Scope::to('articles.edit')->in(''));

    expect($user->can('articles.edit', article($team)))->toBeFalse();
});

it('limits the user to one record', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    $article = article($team);

    Teams::scope(fn () => Scope::to('articles.edit')->on($article));

    expect($user->can('articles.edit', $article))->toBeTrue()
        ->and($user->can('articles.edit', Article::find($article->getKey())))->toBeTrue()
        ->and($user->can('articles.edit', article($team)))->toBeFalse()
        ->and($user->can('articles.edit', $team))->toBeFalse();
});

it('asks the callback for each user and leaves users without a scope alone', function () {
    $team = team();
    $limited = member($team, ['articles.edit', 'articles.delete']);
    $free = member($team, ['articles.edit', 'articles.delete']);

    expect($free->can('articles.delete', $team))->toBeTrue();

    Teams::scope(fn (Authenticatable $user) => $user->is($limited) ? Scope::to('articles.edit') : null);

    expect($limited->can('articles.delete', $team))->toBeFalse()
        ->and($free->can('articles.delete', $team))->toBeTrue();
});

it('limits the gate whatever else answers', function () {
    $team = team();
    $user = member($team, ['articles.edit']);

    Gate::define('publish', fn () => true);
    Gate::before(fn () => true);
    Teams::scope(fn () => Scope::to('articles.edit')->in($team));

    expect(Gate::forUser($user)->allows('publish', $team))->toBeFalse()
        ->and(Gate::forUser($user)->allows('articles.edit', $team))->toBeTrue()
        ->and(Gate::forUser($user)->allows('articles.edit', article(team())))->toBeFalse();
});

it('limits routes and controller checks alike', function () {
    $team = team();
    $user = member($team, ['articles.edit', 'articles.delete']);
    $article = article($team);
    $foreign = article(team());

    Route::model('article', Article::class);
    Route::put('/articles/{article}', fn () => 'ok')->middleware(SubstituteBindings::class)->can('articles.edit', 'article');
    Route::delete('/articles/{article}', function (Article $article) {
        Gate::authorize('articles.delete', $article);

        return 'ok';
    })->middleware(SubstituteBindings::class);

    $this->actingAs($user);
    $this->deleteJson("/articles/{$article->getKey()}")->assertOk();

    Teams::scope(fn () => Scope::to('articles.edit')->in($team));

    $this->putJson("/articles/{$article->getKey()}")->assertOk();
    $this->deleteJson("/articles/{$article->getKey()}")->assertForbidden();
    $this->putJson("/articles/{$foreign->getKey()}")->assertForbidden();
});

it('permits nothing without permissions', function () {
    $team = team();
    $user = member($team, ['articles.edit']);

    Teams::scope(fn () => Scope::to());

    expect($user->can('articles.edit', $team))->toBeFalse()
        ->and($team->owner->can('articles.edit', $team))->toBeFalse();
});

it('limits users by their Sanctum tokens', function () {
    $this->app->register(SanctumServiceProvider::class);
    config(['sanctum.guard' => []]);

    $sanctum = dirname((new ReflectionClass(PersonalAccessToken::class))->getFileName(), 2);
    (require $sanctum.'/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php')->up();

    Teams::scope(function (Authenticatable $user): ?Scope {
        $token = $user instanceof ScopedTokenUser ? $user->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken || $token->can('*')) {
            return null;
        }

        return Scope::to($token->abilities);
    });

    Route::model('article', Article::class);
    Route::middleware(['auth:sanctum', SubstituteBindings::class])->group(function () {
        Route::put('/articles/{article}', fn () => 'ok')->can('articles.edit', 'article');
        Route::delete('/articles/{article}', function (Article $article) {
            Gate::authorize('articles.delete', $article);

            return 'ok';
        });
    });

    $team = team();
    $other = team();
    $user = ScopedTokenUser::findOrFail(member($team, ['articles.edit', 'articles.delete'])->getKey());
    $other->add($user)->as(role($other, 'editor', ['articles.edit']));
    $article = article($team);
    $foreign = article($other);

    $request = ['articles.edit', 'articles.unknown'];
    $permissions = $team->permissions()->whereIn('code', $request)->pluck('code');
    $scoped = $user->createToken('Article integration', $permissions->all());

    expect($scoped->accessToken->abilities)->toBe(['articles.edit']);

    $bare = $user->createToken('bare', []);
    $full = $user->createToken('full', ['*']);

    $call = function (string $method, string $token, Article $article) {
        Auth::forgetGuards();

        return $this->withToken($token)->json($method, "/articles/{$article->getKey()}");
    };

    $call('PUT', $scoped->plainTextToken, $article)->assertOk();
    $call('PUT', $scoped->plainTextToken, $foreign)->assertOk();
    $call('DELETE', $scoped->plainTextToken, $article)->assertForbidden();

    $call('PUT', $bare->plainTextToken, $article)->assertForbidden();

    $call('PUT', $full->plainTextToken, $foreign)->assertOk();
    $call('DELETE', $full->plainTextToken, $article)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($user, 'sanctum')->deleteJson("/articles/{$article->getKey()}")->assertOk();

    $scoped->accessToken->update(['abilities' => ['articles.edit', 'articles.delete']]);
    $call('DELETE', $scoped->plainTextToken, $article)->assertOk();

    $team->member($user)->forbid('articles.edit')->on($article);
    $call('PUT', $scoped->plainTextToken, $article)->assertForbidden();
});

it('follows the permissions of the user as they change', function () {
    $team = team();
    $user = member($team, ['articles.edit', 'articles.delete']);
    $article = article($team);
    $role = $team->member($user)->role();

    Teams::scope(fn () => Scope::to('articles.edit', 'articles.publish'));

    expect($user->can('articles.edit', $article))->toBeTrue()
        ->and($user->can('articles.publish', $article))->toBeFalse();

    $role->grant('articles.publish');

    expect($user->can('articles.publish', $article))->toBeTrue();

    $role->revoke('articles.edit');

    expect($user->can('articles.edit', $article))->toBeFalse();

    $role->grant('articles.edit');

    expect($user->can('articles.edit', $article))->toBeTrue();

    $team->member($user)->assign(role($team, 'reader', ['articles.view']));

    expect($user->can('articles.edit', $article))->toBeFalse()
        ->and($user->can('articles.publish', $article))->toBeFalse();

    $team->member($user)->assign(role($team, 'publisher', ['articles.*']));

    expect($user->can('articles.edit', $article))->toBeTrue()
        ->and($user->can('articles.publish', $article))->toBeTrue()
        ->and($user->can('articles.delete', $article))->toBeFalse();

    $team->remove($user);

    expect($user->can('articles.edit', $article))->toBeFalse()
        ->and($user->can('articles.publish', $article))->toBeFalse();
});
