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
    'handling' => 'abort', // or redirect
]
```

- `register`: auto-register `role`, `permission`, `ability` middleware aliases.
- `handling`: unauthorized strategy (`abort` or `redirect`).

## Models

Use `models` bindings to replace package models with your own implementations.

## Tables and Keys

- `tables.teams`
- `tables.team_user`
- `foreign_keys.team_id`

Set these before running migrations if you need custom schema names.

## Request Decision Cache

```php
'request' => [
    'cache_decisions' => false,
]
```

When enabled, repeated `hasTeamPermission()` checks in one request can reuse cached decisions.

## Invitations

```php
'invitations' => [
    'enabled' => true,
    'routes' => [
        'register' => true,
        'url' => '/invitation/{invitation_id}/accept',
        'middleware' => 'web',
    ],
]
```

> [!NOTE]
> Invitation acceptance route is loaded only when both `enabled` and `routes.register` are `true`.

## Wildcard Permissions

```php
'wildcards' => [
    'enabled' => false,
    'nodes' => ['*', '*.*', 'all'],
]
```

If enabled, these nodes are treated as universal permission matches.

> [!WARNING]
> Wildcards affect permission matching broadly. Keep wildcard nodes minimal and explicit.
