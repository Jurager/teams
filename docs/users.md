---
title: Users
weight: 50
---

# Users

User APIs are provided by `Jurager\Teams\Traits\HasTeams`.

## Team Relations

```php
$user->teams();          // BelongsToMany — teams the user joined (pivot: role_id)
$user->ownedTeams();     // HasMany       — teams the user created
$user->allTeams();       // Collection    — owned + joined, sorted by name
$user->belongsToTeam($team);  // bool — true if owner or member
$user->ownsTeam($team);       // bool — compares user ID to team.user_id
```

> [!NOTE]
> `allTeams()` merges `ownedTeams` and `teams` collections and sorts the result by `name`. It always returns a Collection, never triggers a new query after relations are loaded.

## Role and Permission Checks

```php
$user->teamRole($team);                        // Role model, Owner pseudo-model, or null
$user->hasTeamRole($team, ['admin', 'root']);  // bool — OR by default
$user->hasTeamRole($team, ['admin'], true);    // bool — AND (all roles required)

$user->teamPermissions($team);          // array — all permission codes (role + group)
$user->teamPermissions($team, 'role');  // array — role permissions only
$user->teamPermissions($team, 'group'); // array — group permissions only

$user->hasTeamPermission($team, 'servers.update');
$user->hasTeamPermission($team, ['posts.view', 'posts.edit']);         // OR
$user->hasTeamPermission($team, ['posts.view', 'posts.edit'], true);   // AND
$user->hasTeamPermission($team, 'posts.view', false, 'role');          // scope
```

`hasTeamPermission()` supports:

- single code or array
- OR behavior by default (`$require = false`)
- AND behavior with `$require = true`
- optional `$scope` (`'role'` or `'group'`) to narrow which permission layer is checked

> [!NOTE]
> Team owners always pass all role and permission checks. `teamPermissions()` returns `['*']` for the owner, and `hasTeamPermission()` / `hasTeamRole()` return `true` unconditionally.

> [!WARNING]
> With an empty permissions array, `hasTeamPermission()` returns `false` regardless of `$require`. This is by design — an empty check is treated as a denied access request.

> [!NOTE]
> `teamRole()` returns an `Owner` pseudo-model (with wildcard permission `*`) when the user owns the team. For non-members it returns `null`.

## Request-Lifecycle Cache

When `teams.request.cache_decisions = true` in config, repeated calls to `hasTeamPermission()` with the same arguments within a single HTTP request reuse a cached result stored in `$user->decisionCache`.

> [!WARNING]
> The cache is stored on the user model instance. If you create a fresh model instance during the same request (e.g., via `User::find()`), the cache will not be shared. Disable the cache if you mutate permissions mid-request and need immediate consistency.
