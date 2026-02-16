---
title: Commands
weight: 110
---

# Commands

## `teams:install`

Publishes package assets and copies stubs.

```bash
php artisan teams:install
```

Actions performed:

1. publish config (`teams-config`)
2. publish migrations (`teams-migrations`)
3. publish views (`teams-views`)
4. create app directories used by stubs
5. copy actions/policies/controllers stubs

> [!WARNING]
> The command publishes with `--force`, so local files may be overwritten.
