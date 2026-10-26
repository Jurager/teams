---
title: Models
weight: 130
---

# Models

- [Package Models](#package-models)
- [Custom Models](#custom-models)
- [The Owner Role](#the-owner-role)

## Package Models

| Model        | Relations                                                                      |
|--------------|--------------------------------------------------------------------------------|
| `Team`       | `owner`, `users`, `roles`, `groups`, `permissions`, `abilities`, `invitations` |
| `Role`       | `team`, `users`, `permissions`, `abilities`                                    |
| `Group`      | `team`, `users`, `permissions`, `abilities`                                    |
| `Permission` | `team`, `roles`, `groups`                                                      |
| `Ability`    | `team`, `entity`, `users`, `roles`, `groups`                                   |
| `Invitation` | `team`, `role`, `user`                                                         |
| `Membership` | `team`, `user`, `role`                                                         |

All models live in the `Jurager\Teams\Models` namespace.

## Custom Models

Extend the package model and register your class in the [configuration](configuration.md#models):

```php
namespace App\Models;

use Jurager\Teams\Models\Team as BaseTeam;

class Team extends BaseTeam
{
    protected $fillable = ['user_id', 'name', 'slug'];
}
```

```php
'models' => [
    'team' => App\Models\Team::class,
],
```

If your team model must extend another class, use the `Jurager\Teams\Traits\HasMembers` trait instead. A custom membership model must extend `Membership` or Eloquent's `Pivot`.

> [!NOTE]
> Delete roles and groups with `deleteOrFail()`, so their permissions and rules are removed in the same transaction. `deleteRole()` and `deleteGroup()` already do this.

## The Owner Role

For the team owner, `teamRole()` returns an `Owner` object instead of a `Role` model. It is not stored in the database:

```php
$role = $owner->teamRole($team);

$role->code;        // 'owner'
$role->permissions; // ['*']
```
