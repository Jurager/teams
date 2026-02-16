---
title: Facade & Service
weight: 125
---

# Facade & Service

The package registers `teams` singleton backed by `Jurager\Teams\Support\Services\TeamsService`.

## Facade

Use facade methods:

```php
use Jurager\Teams\Support\Facades\Teams;

$teamClass = Teams::model('team');
$teamModel = Teams::instance('team');
```

## Supported Model Keys

Default keys from `teams.models`:

- `user`
- `team`
- `ability`
- `permission`
- `group`
- `invitation`
- `membership`
- `role`

> [!WARNING]
> Calling `Teams::model()` / `Teams::instance()` with unknown key or non-existing class throws `RuntimeException`.
