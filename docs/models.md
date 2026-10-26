---
title: Models
weight: 130
---

## Package Models

All models live in the `Jurager\Teams\Models` namespace and have the following relationships:

| Model        | Relationships                                                                    |
|--------------|----------------------------------------------------------------------------------|
| `Team`       | `owner`, `members`, `roles`, `groups`, `permissions`, `abilities`, `invitations` |
| `Role`       | `team`, `members`, `permissions`, `abilities`                                    |
| `Group`      | `team`, `members`, `permissions`, `abilities`                                    |
| `Ability`    | `team`, `permission`, `entity`                                                   |
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

The team owner has no role in the database. Instead, `$team->member($owner)->role()` returns an `Owner` object:

```php
$role = $team->member($owner)->role();

$role->code; // 'owner'
$role->name; // 'Owner'
```
