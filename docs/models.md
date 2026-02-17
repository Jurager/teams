---
title: Models
weight: 130
---

# Models

Default model bindings are configured in `teams.models`.

## Core Models

| Model        | Purpose                                                                 |
|--------------|-------------------------------------------------------------------------|
| `Team`       | Team record; loads `roles.permissions` and `groups.permissions` by default |
| `Role`       | Role with code, name, description; detaches permissions/abilities on delete |
| `Permission` | Permission code scoped to a team                                        |
| `Group`      | User group within a team (or global if `team_id = null`)                |
| `Ability`    | Entity-level allow/forbid rule                                          |
| `Membership` | Pivot model for `team_user`; eager-loads `role` relation                |
| `Invitation` | Pending invite record; resolves `user()` relation by email              |
| `Owner`      | Synthetic pseudo-model returned by `teamRole()` for team owners         |

> [!NOTE]
> `Owner` is not an Eloquent model and is not stored in the database. It is a lightweight object with wildcard permission `['*']` returned whenever the queried user is the team owner. Check `$role instanceof Owner` if you need to distinguish owners in your code.

> [!WARNING]
> `Role` and `Group` run `detach` on related permissions and abilities before deleting. If you override these models, ensure the `boot` / `deleting` hooks are preserved, otherwise orphaned records accumulate in `entity_permission` and `entity_ability`.

> [!NOTE]
> `Membership` eager-loads the `role` relation. Accessing `$user->teams` returns `Membership` pivot instances, so `$user->teams->first()->membership->role` resolves without an extra query when teams are loaded with their pivot.

## Custom Models

Override any model in config:

```php
// config/teams.php
'models' => [
    'team' => App\Models\Team::class,
],
```

> [!WARNING]
> When replacing a default model, keep the same relationships and method contracts. Missing relations (e.g., `roles()`, `groups()`, `owner()`) will cause `RuntimeException` or silent failures in trait methods that depend on them.

> [!WARNING]
> Custom models must use the same table names defined in `teams.tables.*`, or explicitly override `getTable()`. Mismatched table names are a common source of "table not found" errors after customization.
