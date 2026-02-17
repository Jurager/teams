---
title: Middlewares
weight: 90
---

# Middlewares

The package auto-registers the following middleware aliases when `teams.middleware.register = true`:

- `role`
- `permission`
- `ability`

## Unauthorized Handling

The behavior on failed checks is controlled by `teams.middleware.handling`:

- `'abort'` — returns HTTP 403 response.
- `'redirect'` — redirects to the configured URL (useful for frontend apps).

> [!NOTE]
> The second middleware argument is treated as the team identifier source. It can be a direct team ID value, a route parameter name, or a request input key — the middleware resolves them in that order.

## Role and Permission Middleware

```php
// Single role check
Route::get('/users', fn () => 'ok')
    ->middleware('role:admin,team_id');

// Multiple roles — OR logic (pipe separator)
Route::get('/users', fn () => 'ok')
    ->middleware('role:admin|root,team_id');

// Multiple roles — AND logic (require flag)
Route::get('/users', fn () => 'ok')
    ->middleware('role:admin|root,team_id,require');

// Permission OR
Route::get('/posts', fn () => 'ok')
    ->middleware('permission:posts.view|posts.edit,team_id');

// Permission AND
Route::get('/posts', fn () => 'ok')
    ->middleware('permission:posts.view|posts.edit,team_id,require');
```

`team_id` can be resolved from:

1. Middleware argument directly (literal value).
2. Route parameter with the key matching `teams.foreign_keys.team_id` in config.
3. Request input (GET/POST).

> [!WARNING]
> If the team cannot be resolved (no matching route parameter or request input), middleware authorization fails and returns the configured handling response.

## Ability Middleware

```php
'middleware' => ['ability:articles.edit,App\\Models\\Article,article_id']
```

The middleware:
1. Resolves the model class from the second argument.
2. Finds the entity by the ID provided via route parameter or request input (third argument).
3. Calls `$user->hasTeamAbility($team, $permission, $entity)`.

> [!WARNING]
> If ability middleware cannot resolve the entity ID or the model instance (e.g., record not found), authorization **fails**. Make sure the entity ID is always present in the route or request for protected endpoints.

> [!NOTE]
> Disabling auto-registration (`teams.middleware.register = false`) lets you register the middleware under custom aliases in your application's `bootstrap/app.php`.
