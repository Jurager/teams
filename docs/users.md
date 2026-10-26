---
title: Users
weight: 50
---

# Users

- [Teams](#teams)
- [Roles](#roles)
- [Permissions](#permissions)

These methods are added to your user model by the `HasTeams` trait.

## Teams

```php
$user->ownedTeams;            // teams the user owns
$user->teams;                 // teams the user is a member of
$user->allTeams();            // both, sorted by name

$user->ownsTeam($team);
$user->belongsToTeam($team);  // owner or member
```

## Roles

```php
$user->teamRole($team);                         // Role, Owner, or null

$user->hasTeamRole($team, 'editor');
$user->hasTeamRole($team, ['editor', 'admin']); // any of the roles
```

## Permissions

A member's permissions come from their role and their groups in the team.

```php
$user->hasTeamPermission($team, 'articles.edit');

// Any of the permissions...
$user->hasTeamPermission($team, ['articles.edit', 'articles.publish']);

// All of the permissions...
$user->hasTeamPermission($team, ['articles.edit', 'articles.publish'], require: true);

// List all permissions...
$user->teamPermissions($team);
```

To check only the role or only the groups, pass `scope: 'role'` or `scope: 'group'`.

> [!NOTE]
> The owner passes every check. Users outside the team fail every check.
