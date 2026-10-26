---
title: Facade
weight: 125
---

# Facade

The `Teams` facade returns the configured model classes and clears the decision cache:

```php
use Jurager\Teams\Support\Facades\Teams;

Teams::model('team');              // 'App\Models\Team'
Teams::instance('role');           // new, unsaved Role model
Teams::forgetCachedPermissions();  // clear the decision cache
```

Use `model()` when your code must work with whatever class is configured. An unknown key throws a `RuntimeException`.
