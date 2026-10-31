---
title: Models
weight: 130
---

# Models

## Package Models

All models live in the `Jurager\Teams\Models` namespace and have the following relationships:

| Model        | Relationships                                                                    |
|--------------|----------------------------------------------------------------------------------|
| `Team`       | `owner`, `members`, `roles`, `groups`, `permissions`, `abilities`, `invitations` |
| `Role`       | `team`, `members`, `permissions`, `abilities`                                    |
| `Group`      | `team`, `members`, `permissions`, `abilities`                                    |
| `Ability`    | `team`, `permission`, `entity`, `recipient`                                      |
| `Invitation` | `team`, `role`, `user`                                                           |
| `Membership` | `team`, `user`, `role`                                                           |
| `Permission` | —                                                                                |

## Custom Models

To customize a model, extend the package model:

```php
namespace App\Models;

use Jurager\Teams\Models\Team as BaseTeam;

class Team extends BaseTeam
{
    protected $fillable = ['user_id', 'name', 'slug'];
}
```

Then register your class in the [configuration](configuration.md#models):

```php
'models' => [
    'team' => App\Models\Team::class,
],
```

Every model except `user` and `team` must extend its package model, otherwise a `RuntimeException` is thrown.

If your team model must extend another class, use the `Jurager\Teams\Traits\HasMembers` trait instead.

## The Owner Role

The team owner has no role in the database. Instead, `$team->member($owner)->role()` returns a `Jurager\Teams\Owner` object:

```php
$role = $team->member($owner)->role();

$role->code; // 'owner'
$role->name; // 'Owner'
```

## Fluent Operations

`Jurager\Teams\Member` holds the user and team context and checks access. It is not an Eloquent model: an owner or an outsider can also have a `Member` context. `Models\Membership` is the persisted team membership pivot.

The `Pending` namespace contains operations completed by the final fluent call:

| Class                | Created by                     | Completed by  |
|----------------------|--------------------------------|---------------|
| `Pending\Membership` | `$team->add($users)`           | `as($role)`   |
| `Pending\Ability`    | `allow`, `forbid`, or `forget` | `on($record)` |
| `Pending\Invitation` | `$team->invite($email)`        | `as($role)`   |

These objects validate and execute their operations inside a transaction. The other responsibilities are grouped as follows:

| Class or trait | Responsibility |
|---|---|
| `Member` | Fluent API for a user in a team, including live role lookup |
| `MemberPermissions` | Permission sources, explicit record snapshots, and access decisions |
| `TeamMembers` | Role changes, removal, ownership transfer, and team purge, with transactions and events |
| `GroupMembers` | Changes to a group's members |
| `HasTeamRelations` | Eloquent relationships of a team |
| `HasMembers` | Team fluent API and lifecycle event registration; includes `HasTeamRelations` |
| `Teams` | Resolving configured model classes |
| `Facades\Teams` | Laravel facade for the model registry |

A custom team model still needs only `HasMembers`. Record snapshots belong to one `Member` context and change only when `load()` is called again. `role()` and team-wide permission checks always query current state.