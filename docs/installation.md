---
title: Installation
weight: 20
---

# Installation

- [Installing the Package](#installing-the-package)
- [Preparing the User Model](#preparing-the-user-model)

## Installing the Package

```shell
composer require jurager/teams

php artisan vendor:publish --tag=teams-migrations
php artisan migrate
```

> [!WARNING]
> To change table names or the team foreign key, publish the [configuration](configuration.md) with `php artisan vendor:publish --tag=teams-config` and edit it **before** running the migrations.

## Preparing the User Model

Add the `HasTeams` trait to your user model:

```php
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasTeams;
}
```

The package expects `App\Models\User` with an `email` column. To use another class, set `models.user` in the [configuration](configuration.md#models).
