---
title: Groups
weight: 80
---

# Groups

Groups let you assign shared permissions and abilities to subsets of users.

## Usage Scope

- User has team permission, but the group forbids an entity action.
- User lacks team permission, but the group allows an entity action.

## Team Groups

```php
$group = $team->addGroup('support', ['tickets.reply']);
$team->updateGroup('support', ['tickets.*']);
$team->deleteGroup('support');
```

## Group Membership

```php
$group->attachUser($user);
$group->detachUser($user);
$group->users();
```

## Global Groups

A group with `team_id = null` works as a global group. Its permissions are checked by `hasGlobalGroupPermissions()` during ability evaluation.

> [!NOTE]
> Group permissions can override role behavior for specific operations.
