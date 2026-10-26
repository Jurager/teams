---
title: Introduction
weight: 10
---

# Introduction

- [Core Concepts](#core-concepts)
- [Choosing the Right Check](#choosing-the-right-check)
- [Requirements](#requirements)

Teams adds team-based access control to Laravel. A user can own teams, join other teams, and have different rights in each of them.

```php
$team = $owner->ownedTeams()->create(['name' => 'Acme']);

$team->addRole('editor', ['articles.view', 'articles.edit']);
$team->addUser($user, 'editor');

$user->hasTeamPermission($team, 'articles.edit'); // true
```

## Core Concepts

| Concept | Description |
|---|---|
| Team | A workspace with one owner and any number of members. |
| Role | Every member has one role in a team. The role grants permissions. |
| Permission | A code such as `articles.edit`. `articles.*` grants all `articles.` permissions. |
| Group | Extra permissions for some members, on top of their roles. |
| Ability | An allow or forbid rule for one record, such as "edit article #42". |

> [!NOTE]
> The team owner has no role and passes every check in their team.

## Choosing the Right Check

| Question | Method |
|---|---|
| Does the user have this role? | [`hasTeamRole()`](users.md#roles) |
| Can the user edit articles in this team? | [`hasTeamPermission()`](users.md#permissions) |
| Can the user edit this article? | [`hasTeamAbility()`](abilities.md) |

## Requirements

- PHP 8.3+
- Laravel 10.30+, 11, 12, or 13
