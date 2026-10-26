---
title: Introduction
weight: 10
---

Teams provides team-based access control for Laravel applications. A user may own teams, join teams owned by others, and have different permissions in each of them.

```php
$team = $owner->teams()->create(['name' => 'Acme']);

$team->roles()->create(['code' => 'editor'])->grant('articles.view', 'articles.edit');

$team->add($user)->as('editor');

$user->can('articles.edit', $team); // true
```

## Core Concepts

| Concept    | Description                                                                         |
|------------|-------------------------------------------------------------------------------------|
| Team       | A workspace with one owner and any number of members.                               |
| Role       | Each member has one role in a team. The role grants permissions.                    |
| Permission | A code such as `articles.edit`. The `articles.*` code grants every `articles.` one. |
| Group      | Extra permissions for some members, on top of their role.                           |
| Ability    | A rule that allows or forbids an action on one record, such as article #42.         |

> [!NOTE]
> The team owner has no role and passes every check in their team.

## Choosing the Right Check

| Question                                 | Check                                                                         |
|------------------------------------------|-------------------------------------------------------------------------------|
| Can the user edit articles in this team? | [`$user->can('articles.edit', $team)`](authorization.md#checking-permissions) |
| Can the user edit this article?          | [`$user->can('articles.edit', $article)`](abilities.md)                       |
| Does the user have this role?            | [`$team->member($user)->is('editor')`](authorization.md#member-checks)        |

## Requirements

- PHP 8.3+
- Laravel 10.30+, 11, 12, or 13
