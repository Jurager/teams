---
title: Validation
weight: 126
---

# Validation

To make sure a value is a role code of a team, use the `Role` rule:

```php
use Jurager\Teams\Rules\Role;

$request->validate([
    'role' => ['required', 'string', new Role($team)],
]);
```

> [!WARNING]
> The rule only checks that the role exists. Whether the current user may assign it is up to your [policy](authorization.md#protecting-team-changes).
