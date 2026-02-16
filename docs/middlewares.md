---
title: Middlewares
weight: 90
---

# Middlewares

The package can auto-register middleware aliases:

- `role`
- `permission`
- `ability`

Controlled by `teams.middleware.register`.

## Role and Permission Middleware

```php
Route::get('/users', fn () => 'ok')
    ->middleware('role:admin|root,team_id');

Route::get('/posts', fn () => 'ok')
    ->middleware('permission:posts.view|posts.edit,team_id');
```

- Pipe (`|`) means OR.
- Third argument `require` enables AND logic.

```php
'middleware' => ['permission:posts.view|posts.edit,team_id,require']
```

`team_id` can be passed as:

- middleware argument
- request input
- route parameter with key from `teams.foreign_keys.team_id`

> [!NOTE]
> The second middleware argument is treated as team identifier source (request/route key or explicit value).

## Ability Middleware

```php
'middleware' => ['ability:articles.edit,App\\Models\\Article,article_id']
```

The middleware resolves model and entity id from route/request, then calls `hasTeamAbility()`.

> [!WARNING]
> If ability middleware cannot resolve entity id or model instance, authorization fails.
