---
title: Middleware
weight: 90
---

# Middleware

Routes may be protected using Laravel's `can` middleware, just like any other [permission check](authorization.md#checking-permissions).

## Protecting Routes

The second argument of `can` is the name of the route parameter that holds the team or the record:

```php
Route::get('/teams/{team}/articles', [ArticleController::class, 'index'])
    ->can('articles.view', 'team');

Route::put('/articles/{article}', [ArticleController::class, 'update'])
    ->can('articles.edit', 'article');
```

> [!WARNING]
> The parameter must be bound to a model. A plain ID isn't a team, so the check fails with a `403` response.

## Checking Roles

In routes, it is better to check permissions than roles.

Grant a permission such as `settings.manage` to the roles that need it, then check that permission:

```php
$team->role('admin')->grant('settings.manage');

Route::get('/teams/{team}/settings', [SettingsController::class, 'show'])
    ->can('settings.manage', 'team');
```

If a route must depend on the role itself, define a gate in the `boot` method of your `AppServiceProvider`:

```php
Gate::define('team-admin', function (User $user, Team $team) {
    return $team->member($user)->is('admin', 'manager');
});
```

```php
Route::get('/teams/{team}/billing', [BillingController::class, 'show'])
    ->can('team-admin', 'team');
```

The team owner passes every role check.

## Failed Checks

When a check fails, Laravel throws an `AuthorizationException`, which becomes a `403` response. To redirect the user instead, render the exception yourself in your application's `bootstrap/app.php` file:

```php
use Illuminate\Auth\Access\AuthorizationException;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AuthorizationException $e) {
        return redirect('/home')->with('error', 'No access.');
    });
})
```
