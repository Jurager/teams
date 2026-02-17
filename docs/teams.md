---
title: Teams
weight: 40
---

# Teams

Team APIs are provided by `Jurager\Teams\Traits\HasMembers` (used by the Team model).

## Member Management

```php
$team->addUser($user, 'admin');
$team->updateUser($user, 'editor');
$team->deleteUser($user);
```

> [!WARNING]
> Team owner cannot be removed or reassigned through `updateUser()` / `deleteUser()`. Both methods throw `RuntimeException` if the passed user is the team owner.

> [!WARNING]
> `addUser()` throws `RuntimeException` in three cases: user is the team owner, user is already a member, or the role code / ID does not exist in the team.

> [!NOTE]
> `addUser()` and `updateUser()` accept a role code (string) **or** role ID (int cast to string). The role must already exist inside the team before you call these methods.

## Membership Checks

```php
$team->owner;                          // BelongsTo — lazy-loaded owner model
$team->users();                        // BelongsToMany — members, excluding owner
$team->allUsers();                     // Collection — members + owner merged
$team->hasUser($user);                 // bool — true if member OR owner
$team->hasUserWithEmail($email);       // bool — checks members by email
$team->userRole($user);                // Role model or null
```

> [!NOTE]
> `hasUser()` returns `true` for the team owner even though the owner is not stored in the `team_user` pivot table.

## Roles and Groups

```php
// Roles
$team->roles();
$team->hasRole('admin');
$team->getRole('admin');
$team->addRole('admin', ['posts.*']);
$team->updateRole('admin', ['posts.view']);
$team->deleteRole('admin');

// Groups
$team->groups();
$team->hasGroup('support');
$team->getGroup('support');
$team->addGroup('support', ['tickets.*']);
$team->updateGroup('support', ['tickets.view']);
$team->deleteGroup('support');
```

> [!NOTE]
> `addRole()` / `addGroup()` auto-create missing permission codes for the current team via `getPermissionIds()`. Permissions that already exist are reused.

> [!WARNING]
> `updateGroup()` with an empty permissions array detaches **all** existing permissions from the group. Pass only the permissions you want to keep.

## Team Permission Proxy

```php
$team->userHasPermission($user, ['servers.update'], true);
```

This calls `$user->hasTeamPermission($team, ...)` internally. The third argument `$require = true` means **all** listed permissions must match.

> [!NOTE]
> If you already have a reference to the user object, calling `$user->hasTeamPermission()` directly is equivalent and avoids an extra indirection.
