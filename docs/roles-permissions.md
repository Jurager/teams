---
title: Roles & Permissions
weight: 60
---

# Roles & Permissions

Roles group permissions per team and are assigned in the membership pivot (`team_user.role_id`).

## Create Team Roles

```php
$team->addRole('admin', [
    'employees.*',
    'articles.*',
    'team.edit',
]);

// Optional: provide a display name and description
$team->addRole('admin', ['employees.*'], 'Administrator', 'Full team access');

$team->addRole('user', [
    'employees.view',
    'articles.view',
]);
```

> [!NOTE]
> Permission codes use dot-notation (e.g., `articles.edit`, `posts.*`). The second argument is an array of permission code strings. Codes are created in the `permissions` table scoped to the team if they don't exist yet.

> [!WARNING]
> `addRole()` throws `RuntimeException` if a role with the same code already exists in the team. Use `updateRole()` to modify an existing role's permissions.

## Update and Delete Roles

```php
// Replace all permissions on an existing role
$team->updateRole('admin', ['posts.view', 'posts.edit'], 'Administrator', 'Full access');

// Remove a role entirely (detaches permissions and abilities first)
$team->deleteRole('admin');
```

> [!WARNING]
> Deleting a role does **not** automatically unassign members that hold that role. Their `team_user.role_id` will become orphaned. Reassign or remove affected members before deleting the role.

## Add or Remove Individual Permissions

`updateRole()` replaces the **entire** permission set on a role. To add or remove specific permissions without touching the rest, use the role's `permissions()` relation directly:

```php
$role = $team->getRole('admin');

// Add permissions, keeping existing ones intact
$role->permissions()->attach($team->getPermissionIds(['posts.publish']));

// Remove specific permissions, keeping the rest intact
$role->permissions()->detach($team->getPermissionIds(['posts.publish']));

// List permissions currently assigned to the role
$role->permissions;
```

> [!NOTE]
> `getPermissionIds()` resolves permission codes to IDs for the team, creating any codes that don't exist yet. Pass raw IDs directly to `attach()`/`detach()` if you already have them.

## Authorization Pattern

Prefer granular permission checks in policies rather than role checks:

```php
// Preferred — policy method
$user->hasTeamPermission($team, 'server:update');

// Avoid where possible — role check
$user->hasTeamRole($team, 'admin');
```

> [!NOTE]
> Checking roles couples your authorization logic to role names. Checking permissions is more resilient — you can rename or restructure roles without touching policy code.

## Wildcard Matching

If wildcard support is enabled in config, checks include generated wildcard nodes (e.g., `posts.*` matches `posts.edit`, `posts.view`, etc.) and optional custom nodes from `teams.wildcards.nodes`.

```php
// config/teams.php
'wildcards' => [
    'enabled' => true,
    'nodes' => ['*', '*.*', 'all'],
],
```

> [!NOTE]
> Wildcard matching applies to the **permission code stored on the role or group**, not to the permission being checked. A user with `posts.*` passes a check for `posts.edit`. A user with `posts.edit` does **not** pass a check for `posts.*`.

> [!WARNING]
> Wildcard nodes (`*`, `*.*`, `all`) grant full access within the team. Keep the list minimal and never assign wildcard permissions automatically based on user input.
