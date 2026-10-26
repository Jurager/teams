---
title: Abilities
weight: 70
---

# Abilities

- [Allowing and Forbidding Actions](#allowing-and-forbidding-actions)
- [Rules for Roles and Groups](#rules-for-roles-and-groups)
- [Record Owners](#record-owners)
- [How Access Is Decided](#how-access-is-decided)

Permissions apply to the whole team: an editor can edit every article. Abilities add exceptions for single records, such as "this editor may not edit article #42".

```php
$user->hasTeamAbility($team, 'articles.edit', $article);
```

Without any rules, `hasTeamAbility()` uses the user's role and group permissions.

## Allowing and Forbidding Actions

```php
$user->allowTeamAbility($team, 'articles.edit', $article);
$user->forbidTeamAbility($team, 'articles.edit', $article);
$user->deleteTeamAbility($team, 'articles.edit', $article);
```

Instead of a model, you may pass its class and ID:

```php
$user->hasTeamAbility($team, 'articles.edit', Article::class, $articleId);
```

To list the user's own rules for a record, use `$user->teamAbilities($team, $article)`.

## Rules for Roles and Groups

Pass `targetEntity` to attach the rule to a role or a group instead of the user:

```php
$user->forbidTeamAbility($team, 'articles.delete', $article, targetEntity: $team->getRole('editor'));
```

## Record Owners

If a model has an `isOwner()` method, its owner always passes:

```php
class Article extends Model
{
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
```

## How Access Is Decided

The team owner, the record owner, and members of a matching [global group](groups.md#global-groups) always pass. For other team members, the first matching step decides:

1. The user's own rules.
2. The rules and permissions of the user's groups.
3. The rules and permissions of the user's role.

At each step a forbid wins over an allow. When nothing matches, access is denied.

> [!WARNING]
> `hasTeamAbility()` does not check that the record belongs to `$team`. Pass the record's own team, for example `$article->team`.
