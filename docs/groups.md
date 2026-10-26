---
title: Groups
weight: 80
---

# Groups

- [Creating Groups](#creating-groups)
- [Adding and Removing Members](#adding-and-removing-members)
- [Updating and Deleting Groups](#updating-and-deleting-groups)
- [Global Groups](#global-groups)

A group gives extra permissions to some members of a team, on top of their roles. A user can be in several groups.

## Creating Groups

```php
$team->addGroup('reviewers', ['articles.publish']);
```

The permissions and the name are optional. Groups are found by code or ID, like roles:

```php
$team->getGroup('reviewers');
$team->hasGroup('reviewers');
```

## Adding and Removing Members

```php
$group->attachUser($user);
$group->detachUser($user);
```

Both methods accept a user or a collection of users. Users outside the team are skipped.

```php
$team->addGroup('reviewers', ['articles.publish'])->attachUser($user);

$user->hasTeamPermission($team, 'articles.publish'); // true
```

## Updating and Deleting Groups

```php
$team->updateGroup('reviewers', ['articles.*']);           // replace permissions
$team->updateGroup('reviewers', name: 'Senior Reviewers');

$team->deleteGroup('reviewers');
```

## Global Groups

A group with an empty `team_id` is global. Its permissions pass [ability checks](abilities.md) in every team, even for users who are not members. This suits staff such as support agents.

```php
use Jurager\Teams\Models\Group;

$support = Group::create(['team_id' => null, 'code' => 'support', 'name' => 'Support']);

$support->permissions()->sync($team->getPermissionIds(['tickets.*']));
$support->attachUser($agent);
```

> [!NOTE]
> Global groups affect `hasTeamAbility()` only. They do not grant membership, a role, or `hasTeamPermission()`.

> [!WARNING]
> The permission codes are stored under the team passed to `getPermissionIds()` and are deleted with it. Use a team that is never deleted.
