---
title: Models
weight: 130
---

# Models

Default model bindings are configured in `teams.models`.

## Core Models

- `Team`
- `Role`
- `Permission`
- `Group`
- `Ability`
- `Membership`
- `Invitation`
- `Owner` (synthetic role object)

## Notable Behavior

- `Team` loads `roles.permissions` and `groups.permissions` by default.
- `Role` and `Group` detach related permissions/abilities on delete.
- `Owner` is a synthetic role-like object with wildcard permissions (`*`).
- `Membership` is a pivot model with eager-loaded `role` relation.
- `Invitation::user()` resolves user by email (`email` -> `email`).

## Custom Models

Override any model in config:

```php
'models' => [
    'team' => App\Models\Team::class,
]
```

Keep the same relationships and expected contracts when replacing defaults.
