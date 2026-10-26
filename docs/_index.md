---
title: Teams
weight: 1
---

Teams provides team-based access control for Laravel. A user may own teams, join teams owned by others, and have a different role in each of them.

Roles and groups grant permissions within a team, and abilities allow or forbid actions on single records. Global groups give staff, such as support agents, access across all teams without joining each one.

## Features

- Team roles and permissions with a fluent API.
- Works with Laravel's Gate: `$user->can()`, `@can`, and the `can` middleware.
- Wildcard permissions such as `articles.*`.
- Team groups that add permissions on top of a member's role.
- Allow and forbid rules on single records for members, roles, and groups.
- Global groups for access across all teams.
- Ownership transfer.
- Email invitations with signed, expiring links.
- Fast permission checks on lists of records.
- Events for team and membership changes.
- Model factories for your tests.

## Requirements

- PHP 8.3+
- Laravel 10.30+, 11, 12, or 13

## Installation

```shell
composer require jurager/teams

php artisan vendor:publish --tag=teams-migrations

php artisan migrate
```

Then add the `HasTeams` trait to your `User` model:

```php
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasTeams;
}
```

See [installation](installation.md) for details.

## Usage

```php
$team = $owner->teams()->create(['name' => 'Acme']);

$team->roles()->create(['code' => 'member'])->grant('articles.view');
$team->groups()->create(['code' => 'editors'])->grant('articles.*');

$team->add($user)->as('member');
$team->group('editors')->add($user);

$team->member($user)->forbid('articles.delete')->on($article);

$user->can('articles.edit', $article);   // true
$user->can('articles.delete', $article); // false
```

Upgrading from 3.x? See the [upgrade guide](upgrade-guide.md).

## Getting Started

- [Introduction](introduction.md)
- [Installation](installation.md)
- [Configuration](configuration.md)
- [Database Schema](schema.md)
- [Upgrade Guide](upgrade-guide.md)

## Usage Guides

- [Teams](teams.md)
- [Authorization](authorization.md)
- [Roles and Permissions](roles-permissions.md)
- [Groups](groups.md)
- [Abilities](abilities.md)
- [Middleware](middlewares.md)
- [Invitations](invitations.md)
- [Validation](validation.md)

## Digging Deeper

- [Events](events.md)
- [Models](models.md)
- [Facade](facade-service.md)
- [Testing](testing.md)
