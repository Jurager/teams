---
title: Roles and Permissions
weight: 60
---

# Roles and Permissions

- [Creating Roles](#creating-roles)
- [Finding Roles](#finding-roles)
- [Updating Roles](#updating-roles)
- [Deleting Roles](#deleting-roles)
- [Changing a Single Permission](#changing-a-single-permission)
- [Wildcards](#wildcards)

A permission is a code such as `articles.edit`. Codes belong to a team and are created automatically when you pass them to a role or group.

## Creating Roles

```php
$team->addRole('editor', ['articles.view', 'articles.edit']);

$team->addRole('admin', ['articles.*'], 'Administrator', 'Manages all articles');
```

The name defaults to the code in StudlyCase. Codes are unique within a team.

## Finding Roles

```php
$team->getRole('editor'); // Role or null
$team->hasRole('editor'); // true or false
```

Roles can be found by code or by ID.

> [!WARNING]
> Numeric values are treated as IDs, so avoid numeric role codes such as `'2024'`.

## Updating Roles

Pass only what you want to change:

```php
$team->updateRole('editor', ['articles.*']);                // replace permissions
$team->updateRole('editor', []);                            // remove all permissions
$team->updateRole('editor', name: 'Content Editor');
$team->updateRole('editor', description: 'Writes articles');
```

## Deleting Roles

```php
$team->deleteRole('editor');
```

> [!NOTE]
> A role assigned to members cannot be deleted. Move them to another role first.

## Changing a Single Permission

`updateRole()` replaces the whole list. To add or remove one permission, use the relation:

```php
$role = $team->getRole('editor');

$role->permissions()->syncWithoutDetaching($team->getPermissionIds(['articles.publish']));

$role->permissions()->detach($team->permissions()->where('code', 'articles.publish')->pluck('id'));
```

## Wildcards

A code ending with `.*` grants everything below it:

```php
$team->addRole('editor', ['articles.*']);

$user->hasTeamPermission($team, 'articles.edit');          // true
$user->hasTeamPermission($team, 'articles.comments.edit'); // true
```

To let a code such as `*` grant every permission, enable [`wildcards`](configuration.md#wildcards) in the configuration.
