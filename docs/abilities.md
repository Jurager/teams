---
title: Abilities
weight: 70
---

A permission applies to every record in the team. An ability allows or forbids an action on one record.

## Checking a Record

To check a permission on a record, pass the record to `can`. The team is taken from the record's `team_id` column:

```php
$user->can('articles.edit', $article);
```

When the record has no rules, the user's role and group permissions decide. A record of another team is always denied.

## Allowing and Forbidding Actions

Rules are added to a member, a role, or a group, and the `on` method names the record they apply to:

```php
$team->member($user)->allow('articles.edit')->on($article);

$team->role('editor')->forbid('articles.delete')->on($article);

$team->group('reviewers')->allow('articles.publish')->on($article);
```

To remove a rule, use the `forget` method:

```php
$team->member($user)->forget('articles.edit')->on($article);
```

Instead of a model, you may pass its class and key:

```php
$team->member($user)->allow('articles.edit')->on(Article::class, $articleId);
```

## Record Owners

If a model has an `isOwner` method, a user for whom it returns `true` always passes:

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

The team owner, the record owner, and members of a matching [global group](groups.md#global-groups) always pass.
For everyone else in the team, the first step that has a rule or a permission decides:

1. The member's own rules.
2. The rules and permissions of the member's groups.
3. The rules and permissions of the member's role.

Within a step, a forbid wins over an allow. When no step matches, access is denied.
