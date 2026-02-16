---
title: Abilities
weight: 70
---

# Abilities

Abilities are entity-level allow/forbid rules on top of team permissions.

## Grant / Forbid

```php
$user->allowTeamAbility($team, 'articles.edit', $article);
$user->forbidTeamAbility($team, 'articles.edit', $article);
$user->deleteTeamAbility($team, 'articles.edit', $article);
```

You may pass a custom target entity as the 4th argument. If omitted, target defaults to the user.

## Check Ability

```php
$user->hasTeamAbility($team, 'articles.edit', $article);
```

The check combines:

1. owner shortcut
2. role/group/global permission checks
3. role/group/user entity abilities (`forbidden` vs allowed levels)

Access is granted when final allowed level is greater than or equal to final forbidden level.

## Access Levels

| Level             | Value |
|-------------------|-------|
| `DEFAULT`         | 0     |
| `FORBIDDEN`       | 1     |
| `ROLE_ALLOWED`    | 2     |
| `ROLE_FORBIDDEN`  | 3     |
| `GROUP_ALLOWED`   | 4     |
| `GROUP_FORBIDDEN` | 5     |
| `USER_ALLOWED`    | 5     |
| `USER_FORBIDDEN`  | 6     |
| `GLOBAL_ALLOWED`  | 6     |

> [!NOTE]
> The package intentionally compares numeric levels (`allowed >= forbidden`) instead of using a strict role/group/user precedence list.

> [!WARNING]
> `GROUP_FORBIDDEN` and `USER_ALLOWED` share value `5`, while `USER_FORBIDDEN` and `GLOBAL_ALLOWED` share value `6`. This tie can be surprising; verify your scenarios with tests.
