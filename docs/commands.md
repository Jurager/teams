---
title: Commands
weight: 110
---

# Commands

## `teams:install`

Publishes package assets and copies stubs into your application.

```bash
php artisan teams:install
```

Actions performed:

1. Publish config (`teams-config` tag → `config/teams.php`)
2. Publish migrations (`teams-migrations` tag → `database/migrations/`)
3. Publish views (`teams-views` tag → `resources/views/vendor/teams/`)
4. Create application directories used by stubs
5. Copy actions, policies, and controller stubs into `app/Actions/Teams`, `app/Policies`, `app/Http/Controllers`

> [!WARNING]
> The command uses `--force` publishing. **All existing files** at the destination paths will be overwritten without confirmation — including `config/teams.php` and any stubs you previously customized. Make a backup before running this command on an existing project.

> [!NOTE]
> After installation, run `php artisan migrate` to create the required database tables. If you plan to use custom table names or foreign keys, edit `config/teams.php` before migrating.
