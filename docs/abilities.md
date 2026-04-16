---
title: Abilities
weight: 70
---

# Abilities

Abilities are entity-level allow/forbid rules layered on top of team permissions. They let you grant or restrict access to a **specific model instance** rather than the entire resource type.

## Grant / Forbid / Delete

```php
// Allow user to edit this specific article (passing a model)
$user->allowTeamAbility($team, 'articles.edit', $article);

// Same, but without loading the model — pass the class name and ID instead
$user->allowTeamAbility($team, 'articles.edit', Article::class, $articleId);

// Forbid user from editing this specific article
$user->forbidTeamAbility($team, 'articles.edit', $article);
$user->forbidTeamAbility($team, 'articles.edit', Article::class, $articleId);

// Remove the ability rule entirely
$user->deleteTeamAbility($team, 'articles.edit', $article);
$user->deleteTeamAbility($team, 'articles.edit', Article::class, $articleId);
```

The optional 4th argument `$target_entity` specifies the entity the rule is attached to. If omitted, the rule targets the user themselves.

> [!NOTE]
> When passing a class name string as `$action_entity`, the entity ID must be provided as the 4th argument. Omitting it will throw an `InvalidArgumentException`.

> [!NOTE]
> `allowTeamAbility()` creates the ability record if it does not exist yet. Subsequent calls update the existing record.

> [!WARNING]
> `deleteTeamAbility()` silently does nothing if the ability does not exist. It does **not** throw an exception.

## Check Ability

```php
// Passing a model
$user->hasTeamAbility($team, 'articles.edit', $article);

// Without loading the model — pass the class name and ID
$user->hasTeamAbility($team, 'articles.edit', Article::class, $articleId);
```

The evaluation order is:

1. **Owner shortcut** — team owner is always granted access.
2. **Team-level permission check** — role, group, and global group permissions.
3. **Role abilities** — entity-specific rules attached to the user's role.
4. **Group abilities** — entity-specific rules attached to the user's group(s).
5. **User abilities** — entity-specific rules attached to the user directly.
6. **Final decision** — `allowed >= forbidden` → access granted.

> [!NOTE]
> Steps 3–5 only run if the entity has at least one ability record. For entities without specific rules, the result is determined purely by team-level permissions.

## Access Levels

Permissions are governed by numeric access levels. Two counters are tracked — `allowed` and `forbidden` — and access is granted when `allowed >= forbidden`.

| Level             | Value | Description                                                                               |
|-------------------|-------|-------------------------------------------------------------------------------------------|
| `DEFAULT`         | 0     | No explicit permission or restriction.                                                    |
| `FORBIDDEN`       | 1     | Base denial (no permission at team level).                                                |
| `ROLE_ALLOWED`    | 2     | Permission granted via the user's role.                                                   |
| `ROLE_FORBIDDEN`  | 3     | Restriction applied via the user's role.                                                  |
| `GROUP_ALLOWED`   | 4     | Permission granted via the user's group.                                                  |
| `GROUP_FORBIDDEN` | 5     | Restriction applied via the user's group.                                                 |
| `USER_ALLOWED`    | 5     | Permission granted specifically for this user on this entity.                             |
| `USER_FORBIDDEN`  | 6     | Restriction applied specifically to this user on this entity.                             |
| `GLOBAL_ALLOWED`  | 6     | Permission from a global group (team-independent).                                        |

> [!WARNING]
> `GROUP_FORBIDDEN` and `USER_ALLOWED` share the same value **5**, and `USER_FORBIDDEN` and `GLOBAL_ALLOWED` both equal **6**. This means a group-forbidden rule ties with a user-allowed rule (neither wins), and a user-forbidden rule ties with a global-allowed rule. Design your ability assignments with these ties in mind and cover edge cases in tests.

> [!NOTE]
> The comparison is `allowed >= forbidden` (not strictly greater). A tie is resolved in favor of **granting** access. For example, `USER_ALLOWED (5) >= GROUP_FORBIDDEN (5)` → access granted.
