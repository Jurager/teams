---
title: Roles and Permissions
weight: 60
---

# Roles and Permissions

A permission is a code such as `articles.edit`. Permissions belong to a team and are created automatically the first time you grant them.

## Creating Roles

Roles are Eloquent models, created using the team's `roles` relationship:

```php
$team->roles()->create(['code' => 'editor'])->grant('articles.view', 'articles.edit');

$team->roles()->create([
    'code' => 'admin',
    'name' => 'Administrator',
    'description' => 'Manages all articles',
])->grant('articles.*');
```

Role codes are unique within a team. If you don't give a name, it is made from the code, so `content-editor` becomes `Content Editor`.

## Granting Permissions

To change the permissions of a role, use the `grant`, `revoke`, and `sync` methods:

```php
$role = $team->role('editor');

$role->grant('articles.publish');
$role->revoke('articles.delete');

// Replace all permissions...
$role->sync('articles.view', 'articles.edit');

// Remove all permissions...
$role->sync();
```

Each method accepts any number of codes or an array, and returns the role, so calls may be chained.

## Finding, Updating, and Deleting Roles

The `role` method finds a role by its code. If the role doesn't exist, a `ModelNotFoundException` is thrown:

```php
$role = $team->role('editor');

$role->update(['name' => 'Content Editor']);

$role->delete();
```

All roles of a team are available through `$team->roles`.

> [!NOTE]
> A role that is assigned to members can't be deleted, so move them to another role first. Deleting a role also deletes its permissions and [ability rules](abilities.md).

## Naming Permissions

Each permission has a name for your screens. By default, it is made from the code, so `articles.edit` is named `Articles Edit`. To give your own names, pass `code => name` pairs to the `grant` or `sync` methods. You may mix them with plain codes:

```php
$team->role('editor')->grant('articles.view', [
    'articles.edit' => 'Edit articles',
    'articles.publish' => 'Publish articles',
]);

$team->role('editor')->permissions->pluck('name');
// ['Articles View', 'Edit articles', 'Publish articles']
```

Permissions are stored in the database once per team, so a name given to one role also changes for every other role with that permission. Granting a code without a name keeps its current name.

The team's permissions are useful for building a form that manages a role's permissions, and for validating it:

```php
$request->validate([
    'permissions.*' => [Rule::in($team->permissions->pluck('code'))],
]);

$team->role('editor')->sync($request->permissions);
```

## Wildcards

A code that ends with `.*` grants every permission below it:

```php
$team->role('editor')->sync('articles.*');

$user->can('articles.edit', $team);          // true
$user->can('articles.comments.edit', $team); // true
```

To let a code such as `*` grant every permission, enable [wildcards](configuration.md#wildcards) in the configuration.
