---
title: Facade & Service
weight: 125
---

# Facade & Service

The package registers a `teams` singleton backed by `Jurager\Teams\Support\Services\TeamsService`.

## Facade

```php
use Jurager\Teams\Support\Facades\Teams;

// Get the FQCN of a bound model
$teamClass = Teams::model('team');       // e.g. "App\Models\Team"

// Create a new (unsaved) model instance
$teamModel = Teams::instance('team');   // new Team()
```

> [!NOTE]
> `Teams::model()` is commonly used internally by traits to resolve the correct Eloquent model class at runtime, respecting any custom bindings in `teams.models`.

## Supported Model Keys

| Key          | Default class                              |
|--------------|--------------------------------------------|
| `user`       | `App\Models\User`                          |
| `team`       | `Jurager\Teams\Models\Team`                |
| `role`       | `Jurager\Teams\Models\Role`                |
| `permission` | `Jurager\Teams\Models\Permission`          |
| `group`      | `Jurager\Teams\Models\Group`               |
| `ability`    | `Jurager\Teams\Models\Ability`             |
| `membership` | `Jurager\Teams\Models\Membership`          |
| `invitation` | `Jurager\Teams\Models\Invitation`          |

> [!WARNING]
> Calling `Teams::model()` or `Teams::instance()` with an unrecognized key, or a key whose configured class does not exist, throws a `RuntimeException`. Always use one of the supported keys listed above, or ensure your custom class is auto-loadable before overriding.
