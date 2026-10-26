---
title: Middleware
weight: 90
---

# Middleware

- [Roles and Permissions](#roles-and-permissions)
- [Selecting the Team](#selecting-the-team)
- [Abilities](#abilities)
- [Failed Checks](#failed-checks)

The package registers three middleware: `permission`, `role`, and `ability`.

## Roles and Permissions

```php
Route::get('/teams/{team_id}/articles', [ArticleController::class, 'index'])
    ->middleware('permission:articles.view');

Route::get('/teams/{team_id}/settings', [SettingsController::class, 'show'])
    ->middleware('role:admin|manager');
```

Separate values with `|`; any one of them is enough. Add `require` as the third argument to require all of them:

```php
->middleware('permission:articles.edit|articles.publish,team_id,require');
```

## Selecting the Team

The team is read from the `team_id` route parameter or request field. Pass a second argument to use another parameter name or a fixed team ID:

```php
Route::get('/teams/{team}/articles', ...)->middleware('permission:articles.view,team');

Route::get('/admin', ...)->middleware('role:admin,1');
```

Route model binding is supported. A route parameter always wins over a request field with the same name.

## Abilities

The arguments are the ability, the model class, and the route parameter holding the record:

```php
Route::get('/teams/{team_id}/articles/{article}/edit', ...)
    ->middleware('ability:articles.edit,'.Article::class.',article');
```

When the record has a `team_id` column, it must belong to the team from the route.

## Failed Checks

Guests and users without access get `403 Forbidden`. An unknown team returns `404 Not Found`. To redirect instead, set `middleware.handling` to `redirect` in the [configuration](configuration.md#middleware).

> [!NOTE]
> If the aliases clash with your own middleware, set `middleware.register` to `false` and register the `Jurager\Teams\Middleware` classes under other names.
