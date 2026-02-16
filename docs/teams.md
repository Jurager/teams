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
> Team owner cannot be removed or reassigned through `updateUser()` / `deleteUser()`.

## Membership Checks

```php
$team->owner;
$team->users();
$team->allUsers();
$team->hasUser($user);
$team->hasUserWithEmail($email);
$team->userRole($user);
```

## Roles and Groups

```php
$team->roles();
$team->hasRole('admin');
$team->getRole('admin');
$team->addRole('admin', ['posts.*']);
$team->updateRole('admin', ['posts.view']);
$team->deleteRole('admin');

$team->groups();
$team->hasGroup('support');
$team->getGroup('support');
$team->addGroup('support', ['tickets.*']);
$team->updateGroup('support', ['tickets.view']);
$team->deleteGroup('support');
```

## Team Permission Proxy

```php
$team->userHasPermission($user, ['servers.update'], true);
```

This calls `$user->hasTeamPermission($team, ...)` internally.

> [!NOTE]
> `addRole()` / `addGroup()` auto-create missing permission codes for the current team via `getPermissionIds()`.
