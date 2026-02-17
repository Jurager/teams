---
title: Configuration
weight: 30
---

# Configuration

Main configuration lives in `config/teams.php`.

## Middleware

```php
'middleware' => [
    'register' => true,
    'handling' => 'abort',

    'handlers' => [
        'abort' => [
            'code'    => 403,
            'message' => 'User does not have any of the necessary access rights.',
        ],
        'redirect' => [
            'url'     => '/home',
            'message' => [
                'key'     => 'error',
                'content' => '',
            ],
        ],
    ],
],
```

- `register` — automatically register middleware aliases in the application.
- `handling` — one of the handler from `handlers`.

> [!NOTE]
> Set `register = false` if you need to bind the middleware under custom aliases in your own `bootstrap/app.php`.

### Handler: `abort`

```php
'abort' => [
    'code'    => 403,
    'message' => 'User does not have any of the necessary access rights.',
],
```

Calls `abort($code, $message)`. Set `code` to any HTTP status code and `message` to the response body text.

### Handler: `redirect`

```php
'redirect' => [
    'url'     => '/home',
    'message' => [
        'key'     => 'error',
        'content' => '',
    ],
],
```

Redirects the request to the specified url. If both `message.key` and `message.content` are non-empty, flashes the message to the session, so it can be read in the next request with `session('error')`.

> [!NOTE]
> To use a flash message on redirect, set both `message.key` and `message.content` to non-empty values.

> [!NOTE]
> If `handling` is not set or does not match any key in `handlers`, the middleware falls back to `abort` with HTTP 403.

## Models

```php
'models' => [
    'team'       => Jurager\Teams\Models\Team::class,
    'role'       => Jurager\Teams\Models\Role::class,
    'permission' => Jurager\Teams\Models\Permission::class,
    'group'      => Jurager\Teams\Models\Group::class,
    'ability'    => Jurager\Teams\Models\Ability::class,
    'membership' => Jurager\Teams\Models\Membership::class,
    'invitation' => Jurager\Teams\Models\Invitation::class,
    'user'       => App\Models\User::class,
],
```

Use `models` bindings to replace package models with your own implementations. See [Models](models.md) for constraints.

## Tables and Keys

```php
'tables' => [
    'teams'             => 'teams',
    'team_user'         => 'team_user',
    'roles'             => 'roles',
    'permissions'       => 'permissions',
    'groups'            => 'groups',
    'group_user'        => 'group_user',
    'abilities'         => 'abilities',
    'entity_permission' => 'entity_permission',
    'entity_ability'    => 'entity_ability',
    'invitations'       => 'invitations',
],

'foreign_keys' => [
    'team_id' => 'team_id',
],
```

> [!WARNING]
> All `tables.*` and `foreign_keys.*` changes must be applied **before** publishing or running migrations. Changing these values after migrations run requires manual schema changes.

## Request Decision Cache

```php
'request' => [
    'cache_decisions' => false,
]
```

When enabled, repeated `hasTeamPermission()` calls within one HTTP request reuse cached results stored on the user model instance (keyed by a SHA-256 hash of arguments).

> [!WARNING]
> Enable this only if your permission data does not change during a request. If you modify roles or permissions mid-request and then re-check, the cache will return stale results. The cache is per-model-instance, so a freshly loaded `User` object starts with an empty cache.

## Invitations

```php
'invitations' => [
    'enabled' => true,
    'routes' => [
        'register'   => true,
        'url'        => '/invitation/{invitation_id}/accept',
        'middleware' => 'web',
    ],
],
```

> [!NOTE]
> The invitation acceptance route is loaded only when both `enabled` and `routes.register` are `true`. Disable `routes.register` if you want to handle acceptance routing manually.

> [!WARNING]
> The `signed` middleware is commented out in the package route file. Enable signed URL validation in production to prevent invitation link forgery.

## Route Group Options

The service provider reads:

- `teams.routes.prefix`
- `teams.routes.middleware`

for the invitation route group wrapper. These are optional — if unset, no prefix or extra middleware is applied.

## Wildcard Permissions

```php
'wildcards' => [
    'enabled' => false,
    'nodes'   => ['*', '*.*', 'all'],
],
```

If enabled, these nodes are treated as universal permission matches (super-admin grants).

> [!WARNING]
> Wildcard nodes grant **all permissions** within the team to any user or role that holds one. Keep the list minimal and never assign wildcard permissions based on untrusted user input.
