---
title: Validation
weight: 126
---

# Validation

The `Role` rule checks that the value is a role code of the given team:

```php
use Jurager\Teams\Rules\Role;

$request->validate([
    'role' => ['required', 'string', new Role($team)],
]);
```

> [!WARNING]
> The rule only checks that the role exists. Whether the current user may assign it is up to your [policy](teams.md#authorization).
