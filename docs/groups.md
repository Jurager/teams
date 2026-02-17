---
title: Groups
weight: 80
---

# Groups

Groups let you assign shared permissions and abilities to subsets of users within a team.

## Usage Scope

Groups work as an **override layer** on top of role permissions:

- User **has** team permission, but the group **forbids** an entity action → entity action is denied.
- User **lacks** team permission, but the group **allows** an entity action → entity action is allowed.

> [!NOTE]
> Group permissions participate in ability level comparison (see [Abilities](abilities.md)). Group rules only affect `hasTeamAbility()` checks, not plain `hasTeamPermission()` checks.

## Team Groups

```php
// Create group — permissions array is required; name defaults to Str::studly($code)
$group = $team->addGroup('support', ['tickets.reply']);

// Replace group permissions (empty array removes all existing permissions)
$team->updateGroup('support', ['tickets.*']);

// Remove group and detach all permissions/abilities
$team->deleteGroup('support');
```

> [!WARNING]
> `updateGroup()` with an empty array **removes all permissions** from the group. There is no partial-update mode — always pass the full desired permission list.

> [!WARNING]
> `addGroup()` throws `RuntimeException` if a group with the same code already exists in the team.

## Group Membership

```php
$group->attachUser($user);   // add user to the group
$group->detachUser($user);   // remove user from the group
$group->users();             // BelongsToMany — all group members
```

> [!NOTE]
> A user can belong to multiple groups within the same team. All matching group rules are evaluated when `hasTeamAbility()` resolves access.

## Global Groups

A group with `team_id = null` is a **global group**. Its permissions are checked by `hasGlobalGroupPermissions()` during ability evaluation and apply across all teams.

```php
// Creating a global group (no team scoping)
$group = Group::create(['code' => 'support-global', 'team_id' => null]);
$group->attachUser($user);
```

> [!NOTE]
> Global groups are useful for cross-team roles (e.g., platform support staff) without adding the user as a member of each individual team.

> [!WARNING]
> Global group permissions are evaluated only inside `hasTeamAbility()`. They are **not** returned by `$user->teamPermissions()` and do not affect `hasTeamPermission()` checks.
