---
title: Roles & Permissions
weight: 60
---

# Roles & Permissions

Roles group permissions per team and are assigned in membership pivot (`team_user.role_id`).

## Create Team Roles

```php
$team->addRole('admin', [
    'employees.*',
    'articles.*',
    'team.edit',
]);

$team->addRole('user', [
    'employees.view',
    'articles.view',
]);
```

## Authorization Pattern

Prefer granular permission checks in policies:

```php
$user->hasTeamPermission($team, 'server:update');
```

## Wildcard Matching

If wildcard support is enabled in config, checks include generated wildcard nodes (for example `posts.*`) and optional custom nodes from `teams.wildcards.nodes`.
