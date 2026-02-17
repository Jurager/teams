---
title: Validation
weight: 126
---

# Validation

The package provides one built-in validation rule:

- `Jurager\Teams\Rules\Role`

## Usage

```php
use Jurager\Teams\Rules\Role as TeamRoleRule;

$request->validate([
    'role' => ['required', new TeamRoleRule($team)],
]);
```

The rule validates that the provided value exists in `$team->roles` by role **code** (not by ID).

> [!NOTE]
> The rule checks the `code` field of loaded team roles. Pass the team model instance — the rule loads `$team->roles` internally. If you pass a team that has no roles, any value will fail validation.

> [!WARNING]
> The rule does not validate that the user making the request is authorized to assign that role. Combine it with an authorization policy or gate check to prevent privilege escalation (e.g., a regular member assigning themselves `admin`).
