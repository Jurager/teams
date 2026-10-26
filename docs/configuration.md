---
title: Configuration
weight: 30
---

# Configuration

- [Models](#models)
- [Tables and Foreign Keys](#tables-and-foreign-keys)
- [Middleware](#middleware)
- [Decision Cache](#decision-cache)
- [Wildcards](#wildcards)
- [Invitations](#invitations)

All options live in `config/teams.php`. Publish the file to change them:

```shell
php artisan vendor:publish --tag=teams-config
```

## Models

The `models` option maps each package model to a class. List only the models you replace:

```php
'models' => [
    'user' => App\Models\User::class,
    'team' => App\Models\Team::class,
],
```

Available keys: `user`, `team`, `membership`, `invitation`, `role`, `permission`, `ability`, `group`. See [custom models](models.md#custom-models).

## Tables and Foreign Keys

The `tables` option maps each table to its name, and `foreign_keys.team_id` sets the team column used by all package tables. List only the keys you change:

```php
'tables' => [
    'teams' => 'workspaces',
],

'foreign_keys' => [
    'team_id' => 'workspace_id',
],
```

> [!WARNING]
> Change these before running the migrations. Renaming them on an existing database requires your own migration.

## Middleware

| Option | Description |
|---|---|
| `register` | Register the `role`, `permission`, and `ability` aliases. |
| `handling` | What happens when a check fails: `abort` or `redirect`. |
| `handlers.abort` | Status code and message for `abort`. Defaults to `403`. |
| `handlers.redirect` | URL for `redirect`. A non-empty `message.content` is flashed to the session under `message.key`. |

See [middleware](middlewares.md).

## Decision Cache

Enable the cache to remember `hasTeamPermission()` results for the rest of the request:

```php
'request' => [
    'cache_decisions' => true,
],
```

The cache lives for one HTTP request, Octane request, or queued job. It is cleared automatically when the package models change and when a transaction commits or rolls back.

> [!WARNING]
> Direct pivot writes outside a transaction, such as `$role->permissions()->sync(...)`, are not tracked. Clear the cache yourself afterwards with `Teams::forgetCachedPermissions()`.

## Wildcards

When `wildcards.enabled` is `true`, any code listed in `wildcards.nodes` (`*`, `*.*`, and `all` by default) grants every permission. See [wildcards](roles-permissions.md#wildcards).

## Invitations

| Option | Description |
|---|---|
| `enabled` | Turn invitations on or off. |
| `expire` | Link lifetime in minutes, 7 days by default. `null` means the link never expires. |
| `routes.register` | Register the built-in acceptance route. |
| `routes.url` | Route URL. It must contain `{invitation_id}`. |
| `routes.prefix` | Route prefix. |
| `routes.middleware` | Route middleware, `['web', 'auth']` by default. |

See [invitations](invitations.md).
