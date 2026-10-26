---
title: Configuration
weight: 30
---

# Configuration

All the package options are stored in the `config/teams.php` file. To change them, publish the file:

```shell
php artisan vendor:publish --tag=teams-config
```

## Models

The `models` option defines the class used for each package model. You only need to list the models you replace:

```php
'models' => [
    'user' => App\Models\User::class,
    'team' => App\Models\Team::class,
],
```

The available keys are `user`, `team`, `membership`, `invitation`, `role`, `permission`, `ability`, and `group`. Your classes must extend the package models. See [custom models](models.md#custom-models).

## Tables and Foreign Keys

The `tables` option defines the name of each table, and `foreign_keys.team_id` defines the team column used by every package table. You only need to list the values you change:

```php
'tables' => [
    'teams' => 'workspaces',
],

'foreign_keys' => [
    'team_id' => 'workspace_id',
],
```

> [!WARNING]
> Change these options before running the migrations. Renaming tables on an existing database requires your own migration.

## Gate

By default, the package answers Laravel's Gate for team permissions, so `$user->can('articles.edit', $team)`, `@can`, and the `can` middleware work out of the box. To turn this off, set the `gate` option to `false`. See [authorization](authorization.md#checking-permissions).

## Wildcards

When `wildcards.enabled` is `true`, any code listed in `wildcards.nodes` grants every permission. The default nodes are `*`, `*.*`, and `all`. See [wildcards](roles-permissions.md#wildcards).

## Invitations

| Option              | Description                                                                      |
|---------------------|----------------------------------------------------------------------------------|
| `enabled`           | Turns invitations on or off.                                                     |
| `expire`            | Link lifetime in minutes. 7 days by default, `null` for links that never expire. |
| `routes.register`   | Registers the built-in route that accepts invitations.                           |
| `routes.url`        | The route URL. It must contain `{invitation_id}`.                                |
| `routes.prefix`     | The route prefix.                                                                |
| `routes.middleware` | The route middleware. `['web', 'auth']` by default.                              |

See [invitations](invitations.md).
