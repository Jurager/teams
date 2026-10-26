---
title: Validation
weight: 126
---

# Validation

The package provides validation rules for the values your team forms receive. Validating these values first returns a validation error to the user instead of an exception.

## Roles and Groups

To make sure a value is a role code or a group code of a team, use the `Role` and `Group` rules:

```php
use Jurager\Teams\Rules\Group;
use Jurager\Teams\Rules\Role;

$request->validate([
    'role' => ['required', 'string', new Role($team)],
    'group' => ['required', 'string', new Group($team)],
]);
```

> [!WARNING]
> These rules only check that the role or group exists. Whether the current user may assign it is up to your [policy](authorization.md#protecting-team-changes).

## Members

To make sure a value is the ID of a team member, use the `Member` rule. This is useful before changing a member's role or removing them:

```php
use Jurager\Teams\Rules\Member;

$request->validate([
    'user_id' => ['required', new Member($team)],
]);

$team->member(User::find($request->user_id))->assign($request->role);
```

The team owner isn't a member, so the rule rejects them by default. To accept the owner as well, for example when adding users to a group, pass `owner: true`:

```php
'user_id' => ['required', new Member($team, owner: true)],
```

## Invitations

To make sure an email doesn't belong to the owner or a member of the team, use the `Invitable` rule:

```php
use Jurager\Teams\Rules\Invitable;

$request->validate([
    'email' => ['required', 'email', new Invitable($team)],
    'role' => ['required', 'string', new Role($team)],
]);

$team->invite($request->email)->as($request->role);
```

## Unique Codes

Role codes are unique within a team. To validate a new code, use Laravel's `unique` rule with the configured table and team foreign key:

```php
use Illuminate\Validation\Rule;

$request->validate([
    'code' => [
        'required',
        'string',
        Rule::unique(config('teams.tables.roles'), 'code')
            ->where(config('teams.foreign_keys.team_id'), $team->getKey()),
    ],
]);
```

When updating a role, ignore the role itself with `->ignore($role)`. Group codes work the same way with the `teams.tables.groups` table.
