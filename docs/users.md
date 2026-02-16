---
title: Users
weight: 50
---

# Users

User APIs are provided by `Jurager\Teams\Traits\HasTeams`.

## Team Relations

```php
$user->teams();
$user->ownedTeams();
$user->allTeams();
$user->belongsToTeam($team);
$user->ownsTeam($team);
```

## Role and Permission Checks

```php
$user->teamRole($team);
$user->hasTeamRole($team, ['admin', 'root']);
$user->teamPermissions($team);        // all scopes
$user->teamPermissions($team, 'role');
$user->teamPermissions($team, 'group');
$user->hasTeamPermission($team, 'servers.update');
```

`hasTeamPermission()` supports:

- single code or array
- OR behavior by default
- AND behavior with `$require = true`
- optional `scope` (`role` or `group`)

> [!NOTE]
> Team owners always pass role/permission checks.

> [!WARNING]
> With an empty permissions array, `hasTeamPermission()` returns `false` in the current implementation.
