# Jurager Teams

[![Latest Stable Version](https://poser.pugx.org/jurager/teams/v/stable)](https://packagist.org/packages/jurager/teams)
[![Total Downloads](https://poser.pugx.org/jurager/teams/downloads)](https://packagist.org/packages/jurager/teams)
[![License](https://poser.pugx.org/jurager/teams/license)](LICENSE.md)

A Laravel package for managing teams and user permissions, supporting multi-tenant dynamic roles, role groups, and team-specific permissions.

Users can be organized into groups within teams, each with custom permissions and abilities. Group permissions add to the permissions of the member's role. For a specific record, the user's own rules are checked first, then their groups' rules, then their role's.

Additionally, users can be added to a global group to grant them access across all teams with the group's permissions. This feature is ideal for scenarios like providing support across multiple teams without the need to add users to each team individually.

## Features

- Team-scoped roles and permissions with a fluent API.
- Works with Laravel's Gate: `$user->can()`, `@can`, and the `can` middleware.
- Hierarchical wildcard permissions (`articles.*`).
- Team groups that extend role permissions.
- Record-level allow and forbid rules for members, roles, and groups.
- Global groups for cross-team access.
- Ownership transfer.
- Email invitations.
- Fast permission checks on lists of records.
- Team and membership lifecycle events.
- Model factories for your tests.

## Requirements

- PHP 8.3+
- Laravel 10.30+, 11, 12, or 13

## Installation

```bash
composer require jurager/teams
php artisan vendor:publish --tag=teams-migrations
php artisan migrate
```

Add `Jurager\Teams\Traits\HasTeams` to your User model:

```php
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasTeams;
}
```

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

See the [documentation](https://docs.gerassimov.me/teams/) for groups, route protection, invitations, and configuration.

## Upgrading

Upgrading from 3.x? See the [upgrade guide](https://docs.gerassimov.me/teams/upgrade-guide/).

## Development

```bash
composer install
composer test
composer format:test
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
