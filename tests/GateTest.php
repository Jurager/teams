<?php

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\User;

beforeEach(fn () => Route::model('team', Team::class));

it('authorizes users on a team', function () {
    $team = team();
    $user = member($team, ['articles.view', 'articles.edit']);

    expect($user->can('articles.edit', $team))->toBeTrue()
        ->and($user->can('articles.delete', $team))->toBeFalse()
        ->and($user->can(['articles.view', 'articles.edit'], $team))->toBeTrue()
        ->and($user->can(['articles.view', 'articles.delete'], $team))->toBeFalse()
        ->and($user->canAny(['articles.delete', 'articles.edit'], $team))->toBeTrue()
        ->and($team->owner->can('anything', $team))->toBeTrue()
        ->and(user()->can('articles.edit', $team))->toBeFalse();
});

it('authorizes users on a record through its team', function () {
    $team = team();
    $user = member($team, ['articles.*']);
    $article = article($team);

    expect($user->can('articles.edit', $article))->toBeTrue()
        ->and($user->can('articles.edit', article(team())))->toBeFalse();

    $team->member($user)->forbid('articles.edit')->on($article);

    expect($user->can('articles.edit', $article))->toBeFalse()
        ->and($user->can('articles.view', $article))->toBeTrue();
});

it('asks the application gate first', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    Gate::define('articles.edit', fn () => false);

    expect($user->can('articles.edit', $team))->toBeFalse();
});

it('allows later after callbacks to grant undefined abilities', function () {
    $team = team();
    $user = member($team);
    Gate::after(fn () => true);

    expect($user->can('articles.edit', $team))->toBeTrue();
});

it('preserves boolean results for typed after callbacks', function (bool $allowed) {
    $team = team();
    $user = member($team, $allowed ? ['articles.edit'] : []);
    $results = [];
    Gate::after(function (User $user, string $ability, ?bool $result, array $arguments) use (&$results) {
        $results[] = $result;
    });

    expect($user->can('articles.edit', $team))->toBe($allowed)
        ->and($results)->toBe([$allowed ? true : null]);
})->with([false, true]);

it('keeps explicit application denials when later callbacks grant', function () {
    $team = team();
    Gate::define('articles.edit', fn () => false);
    Gate::after(fn () => true);

    expect($team->owner->can('articles.edit', $team))->toBeFalse();
});

it('leaves other subjects and guests alone', function () {
    $team = team();
    $user = member($team, ['articles.edit']);

    expect($user->can('articles.edit'))->toBeFalse()
        ->and($user->can('articles.edit', user()))->toBeFalse()
        ->and(Gate::allows('articles.edit', $team))->toBeFalse();
});

it('uses team permissions in blade and routes', function () {
    $team = team();
    $user = member($team, ['articles.edit']);
    Route::get('/teams/{team}/edit', fn () => 'ok')->middleware([SubstituteBindings::class, Authorize::class.':articles.edit,team']);
    Route::get('/teams/{team}/delete', fn () => 'ok')->middleware([SubstituteBindings::class, Authorize::class.':articles.delete,team']);

    $this->actingAs($user);

    expect(trim(Blade::render("@can('articles.edit', \$team) yes @endcan", ['team' => $team])))->toBe('yes');
    $this->getJson("/teams/{$team->id}/edit")->assertOk();
    $this->getJson("/teams/{$team->id}/delete")->assertForbidden();
});

it('protects a route with a role gate', function () {
    $team = team();
    role($team, 'admin');
    $admin = user();
    $team->add($admin)->as('admin');
    Gate::define('team-admin', fn ($user, $team) => $team->member($user)->is('admin', 'manager'));
    Route::get('/teams/{team}/billing', fn () => 'ok')->middleware([SubstituteBindings::class, Authorize::class.':team-admin,team']);

    $this->actingAs($admin)->getJson("/teams/{$team->id}/billing")->assertOk();
    $this->actingAs($team->owner)->getJson("/teams/{$team->id}/billing")->assertOk();
    $this->actingAs(member($team))->getJson("/teams/{$team->id}/billing")->assertForbidden();
});
