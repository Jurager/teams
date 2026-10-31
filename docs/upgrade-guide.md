---
title: Upgrade Guide
weight: 38
---

# Upgrade Guide

## Upgrading to the New Ability Schema

This update changes database rows, event properties, and public imports. Existing fluent calls remain the same.

### Migration

Back up your database and stop application writes, including queue workers, during the migration. Publish the new migration without overwriting already published files:

```shell
php artisan vendor:publish --tag=teams-migrations
php artisan migrate
```

Apply the existing `add_expires_at_to_entity_ability_table` migration first if it has not run. The new `2026_10_28_000002_merge_ability_assignments` migration copies every assignment into a new `abilities` row with a single `INSERT … SELECT`, checks the row count, removes `entity_ability` and the old definitions, then gives the new table the configured abilities name. Table names and the configured team key are retained.

Decisions, deadlines, morph types, record and recipient keys, and assignment timestamps are preserved. New ability IDs are generated; shared definitions cannot keep a single ID across multiple recipient rows. Unassigned definitions are discarded. Update any application references to old ability IDs.

This migration is forward-only. To return to the previous schema, restore the backup together with the previous package version. MySQL schema changes are not transactional; do not assume a failed migration rolls back its DDL. If a copy fails before replacement, the original tables remain. Inspect the temporary `<abilities>_new` table and the error before removing it and retrying.

### External Record Calls

A string type and numeric ID can be used with the fluent API without creating a local model:

```php
// Before:
$user->allowTeamAbility($team, 'products.update', 'products', 42);
$user->hasTeamAbility($team, 'products.update', 'products', 42);

// Now:
$team->member($user)->allow('products.update')->on('products', 42);
$team->member($user)->can('products.update', 'products', 42);
```

To assign a rule to a role or group, call its `allow` or `forbid` method directly. Keep the stored external type and numeric ID unchanged. The conversion migration above is still required for the new ability schema; supporting external types requires no additional migration. See [external records](abilities.md#external-records) for team context and model-type restrictions.

### Queries and Events

- User, role, and group `abilities()` relations are now `MorphMany`. Use `whereMorphedTo('recipient', $model)` instead of pivot filters.
- `$team->rules($record)` returns one row per recipient and eagerly loads `permission` and `recipient`. Replace `users`, `roles`, and `groups` with `recipient`; read `forbidden` and `expires_at` directly on the ability.
- `AbilityAssignment` and ability pruning are removed. Remove scheduled `model:prune` jobs for the ability model. Invitation pruning is unchanged.
- Update `AbilityUpdated` listeners to the [value payload](events.md). It no longer contains `ability` or `target` models. This allows queued listeners to handle deleted rules.
- Keep `teams.tables.entity_ability` in published configuration until the historical and conversion migrations have run. Runtime code no longer uses that table.

### Imports

| Previous import                               | New import                         |
|-----------------------------------------------|------------------------------------|
| `Jurager\Teams\Support\Member`                | `Jurager\Teams\Member`             |
| `Jurager\Teams\Models\Owner`                  | `Jurager\Teams\Owner`              |
| `Jurager\Teams\Support\PendingMember`         | `Jurager\Teams\Pending\Membership` |
| `Jurager\Teams\Support\PendingRule`           | `Jurager\Teams\Pending\Ability`    |
| `Jurager\Teams\Support\PendingInvitation`     | `Jurager\Teams\Pending\Invitation` |
| `Jurager\Teams\Support\Services\TeamsService` | `Jurager\Teams\Teams`              |
| `Jurager\Teams\Support\Facades\Teams`         | `Jurager\Teams\Facades\Teams`      |

If your application registers the facade alias manually, update its class and clear the configuration cache. Composer's package discovery alias is updated automatically.

## Upgrading to 4.2.1

Publish and run the new migration:

```shell
php artisan vendor:publish --tag=teams-migrations
php artisan migrate
```

The migration adds a nullable `expires_at` column to your configured `entity_ability` table. Existing assignments remain permanent.

## High Impact Changes

- [Updating Dependencies](#updating-dependencies)
- [Database Changes](#database-changes)
- [Teams and Members](#teams-and-members)
- [Roles, Groups, and Permissions](#roles-groups-and-permissions)
- [Permission Checks](#permission-checks)
- [Middleware](#middleware)

## Medium Impact Changes

- [Record Rules](#record-rules)
- [Invitations](#invitations)
- [Custom Models](#custom-models)
- [Configuration](#configuration)

## Low Impact Changes

- [Exceptions](#exceptions)
- [Events](#events)
- [The Install Command](#the-install-command)
- [The Owner Role](#the-owner-role)
- [The Facade](#the-facade)

## Upgrading to 4.0 from 3.x

#### Estimated Upgrade Time: 1 Hour

> [!NOTE]
> We try to document every breaking change. Some of them are in rarely used parts of the package, so only part of this guide may affect your application.

### Updating Dependencies

**Likelihood Of Impact: High**

Teams 4.0 requires PHP 8.3+ and Laravel 10.30+. Update the dependency in your `composer.json`:

```json
"jurager/teams": "^4.0"
```

### Database Changes

**Likelihood Of Impact: High**

Create a migration in your application that applies the changes below. Use your configured table names and team foreign key.

- Make `permissions.team_id` nullable. Global groups keep their permissions there.
- Drop the unused `abilities.title` column.
- Add the following unique indexes, after removing any duplicate rows:

| Table               | Unique columns                                         |
|---------------------|--------------------------------------------------------|
| `abilities`         | `team_id`, `permission_id`, `entity_type`, `entity_id` |
| `entity_ability`    | `ability_id`, `entity_type`, `entity_id`               |
| `entity_permission` | `permission_id`, `entity_type`, `entity_id`            |
| `group_user`        | `group_id`, `user_id`                                  |

### Teams and Members

**Likelihood Of Impact: High**

The user and team methods have been replaced by a fluent API. On the user model, `teams` returns owned teams, `memberships` returns joined teams, and `accessible()` queries both:

| 3.x                                                    | 4.0                                     |
|--------------------------------------------------------|-----------------------------------------|
| `$user->ownedTeams()`                                  | `$user->teams()`                        |
| `$user->teams()`                                       | `$user->memberships()`                  |
| `$user->allTeams()`                                    | `$user->accessible()->get()`            |
| `$user->ownsTeam($team)`                               | `$user->owns($team)`                    |
| `$user->belongsToTeam($team)`, `$team->hasUser($user)` | `$user->in($team)`                      |
| `$team->users`                                         | `$team->members`                        |
| `$team->allUsers()`, `$team->hasUserWithEmail($email)` | Removed                                 |
| `$team->addUser($user, 'editor')`                      | `$team->add($user)->as('editor')`       |
| `$team->updateUser($user, 'admin')`                    | `$team->member($user)->assign('admin')` |
| `$team->deleteUser($user)`                             | `$team->remove($user)`                  |
| `$team->userRole($user)`, `$user->teamRole($team)`     | `$team->member($user)->role()`          |
| `$user->hasTeamRole($team, 'editor')`                  | `$team->member($user)->is('editor')`    |

Roles are passed by code or as a `Role` model. Role IDs are no longer accepted.

### Roles, Groups, and Permissions

**Likelihood Of Impact: High**

Roles and groups are created through their Eloquent relations, and permissions are changed on the role or group itself:

```php
$team->roles()->create(['code' => 'editor'])->grant('articles.view', 'articles.edit');

$team->role('editor')->grant('articles.publish');
$team->role('editor')->revoke('articles.delete');
$team->role('editor')->sync('articles.*');
```

| 3.x                                             | 4.0                                                                     |
|-------------------------------------------------|-------------------------------------------------------------------------|
| `$team->addRole('editor', [...])`               | `$team->roles()->create(['code' => 'editor'])->grant()`                 |
| `$team->getRole('editor')`, `$team->hasRole()`  | `$team->role('editor')`, `$team->roles()->where('code', ...)->exists()` |
| `$team->updateRole('editor', [...], name: ...)` | `$team->role('editor')->sync()`, `->update()`                           |
| `$team->deleteRole('editor')`                   | `$team->role('editor')->delete()`                                       |
| `$team->getPermissionIds([...])`                | `grant()`, `revoke()`, `sync()`                                         |
| `$group->attachUser($user)`                     | `$group->add($user)`                                                    |
| `$group->detachUser($user)`                     | `$group->remove($user)`                                                 |

The group methods (`addGroup`, `getGroup`, `updateGroup`, `deleteGroup`) follow the same pattern through `$team->groups()` and `$team->group()`. `role()` and `group()` throw `ModelNotFoundException` when the code does not exist.

When no name is given, roles and groups are named after their code, such as `Content Editor` for `content-editor`.

### Permission Checks

**Likelihood Of Impact: High**

Permissions are checked through Laravel's Gate. Pass the team, or a record with a `team_id` column:

| 3.x                                                       | 4.0                                          |
|-----------------------------------------------------------|----------------------------------------------|
| `$user->hasTeamPermission($team, 'articles.edit')`        | `$user->can('articles.edit', $team)`         |
| `$user->hasTeamPermission($team, [...])`                  | `$user->canAny([...], $team)`                |
| `$user->hasTeamPermission($team, [...], require: true)`   | `$user->can([...], $team)`                   |
| `$user->hasTeamAbility($team, 'articles.edit', $article)` | `$user->can('articles.edit', $article)`      |
| `$user->teamPermissions($team)`                           | `$team->member($user)->permissions()`        |
| `$team->userHasPermission($user, 'articles.edit')`        | `$team->member($user)->can('articles.edit')` |

The `scope` argument has been removed. A gate or policy method with the same name as a permission is asked first, so remove such methods or return `null` from them. See [authorization](authorization.md).

### Middleware

**Likelihood Of Impact: High**

The `permission`, `ability`, and `role` middleware have been removed. Use Laravel's `can` middleware with a bound route parameter:

```php
Route::get('/teams/{team}/articles', ...)->can('articles.view', 'team');

Route::put('/articles/{article}', ...)->can('articles.edit', 'article');
```

For role checks, grant a permission to the role and check the permission, or define a gate. See [checking roles](middlewares.md#checking-roles).

### Record Rules

**Likelihood Of Impact: Medium**

Record rules are created on the member, role, or group they belong to:

| 3.x                                                          | 4.0                                                           |
|--------------------------------------------------------------|---------------------------------------------------------------|
| `$user->allowTeamAbility($team, 'articles.edit', $article)`  | `$team->member($user)->allow('articles.edit')->on($article)`  |
| `$user->forbidTeamAbility(..., targetEntity: $role)`         | `$role->forbid('articles.edit')->on($article)`                |
| `$user->deleteTeamAbility($team, 'articles.edit', $article)` | `$team->member($user)->forget('articles.edit')->on($article)` |

`teamAbilities()` has been removed; query the `$user->abilities()` relation instead. Global groups cannot have record rules.

### Invitations

**Likelihood Of Impact: Medium**

Invitations are sent with `invite()` and accepted on the invitation itself:

```php
$invitation = $team->invite('jane@example.com')->as('editor');

$invitation->accept();
```

The built-in route now requires a signed-in user whose email matches the invitation. If you changed `invitations.routes.middleware`, add `auth` to it. New links expire after 7 days, configured by `invitations.expire`. Links sent before the upgrade have no expiry.

### Custom Models

**Likelihood Of Impact: Medium**

Custom `membership`, `invitation`, `role`, `permission`, `ability`, and `group` models must extend the package models. A class that does not throws a `RuntimeException`.

The `users` relations on teams, roles, and groups have been renamed to `members`. The relations of `Permission` have been removed.

### Configuration

**Likelihood Of Impact: Medium**

Remove the `request` and `middleware` blocks from your published `config/teams.php`. The new `gate` option turns the Gate integration on or off and is `true` by default.

### Exceptions

**Likelihood Of Impact: Low**

A missing role, group, or invitation throws `ModelNotFoundException` instead of `RuntimeException`.

### Events

**Likelihood Of Impact: Low**

`TeamCreating` receives the team being created instead of its owner, so listeners read `$event->team`. The "after" events are dispatched once the database transaction commits.

### The Install Command

**Likelihood Of Impact: Low**

The `teams:install` command has been removed. Delete the `InviteController` it published; the package ships its own. Keep your `TeamPolicy` and register it with `Gate::policy()`.

### The Owner Role

**Likelihood Of Impact: Low**

The `Owner` role has a `null` ID, and its `permissions` and `description` properties have been removed.

### The Facade

**Likelihood Of Impact: Low**

The decision cache has been removed, along with `Teams::forgetCachedPermissions()`. `Teams::instance()` has been removed; use `new (Teams::model('role'))` instead.
