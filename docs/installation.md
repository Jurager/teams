---
title: Installation
weight: 20
---

# Installation

Install the package, publish assets, and run migrations.

## Install

```bash
composer require jurager/teams
```

## Publish Package Files

```bash
php artisan teams:install
```

The command publishes:

- `config/teams.php`
- package migrations
- package views
- stubs into `app/Actions/Teams`, `app/Policies`, `app/Http/Controllers`

> [!WARNING]
> `teams:install` uses `--force` publishing. Make a backup before running it in existing projects.

## Run Migrations

```bash
php artisan migrate
```

> [!NOTE]
> If you need custom table names or foreign keys, edit `config/teams.php` before migration.

## Add User Trait

Add `HasTeams` to your existing User model:

```php
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasTeams;
}
```
