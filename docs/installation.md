---
title: Installation
weight: 20
---

# Installation

## Installing the Package

You may install Teams using the Composer package manager:

```shell
composer require jurager/teams
```

Next, publish the migrations and run them:

```shell
php artisan vendor:publish --tag=teams-migrations

php artisan migrate
```

> [!WARNING]
> To rename the tables or the team foreign key, publish the [configuration](configuration.md) file with `php artisan vendor:publish --tag=teams-config` and update it **before** running the migrations.

## Preparing the User Model

Next, add the `HasTeams` trait to your `User` model:

```php
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasTeams;
}
```

By default, the package uses the `App\Models\User` model, which must have an `email` column. You may use another model by changing the `models.user` [configuration](configuration.md#models) option.
