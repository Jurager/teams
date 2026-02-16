---
title: Validation
weight: 126
---

# Validation

The package provides validation rule:

- `Jurager\Teams\Rules\Role`

## Example

```php
use Jurager\Teams\Rules\Role as TeamRoleRule;

$request->validate([
    'role' => [new TeamRoleRule($team)],
]);
```

The rule validates that provided value exists in `$team->roles` by role `code`.

> [!NOTE]
> Validation checks role codes, not role IDs.
