---
title: Teams
weight: 40
---

# Teams

## Creating Teams

Teams are created using the `teams` relationship of the user who will own the team:

```php
$team = $user->teams()->create(['name' => 'Acme']);
```

Before adding members, create at least one [role](roles-permissions.md):

```php
$team->roles()->create(['code' => 'editor'])->grant('articles.view', 'articles.edit');
```

## Managing Members

### Adding Members

To add a user to a team, call the `add` method and pass the role code or a `Role` model to `as`:

```php
$team->add($user)->as('editor');
```

Pass an array, collection, or iterable of users to add several members at once:

```php
$members = $team->add($users)->as('editor');
```

A single user returns a `Member`; an iterable returns a collection of members. Duplicate user keys are processed once.

### Changing Roles

To give a member another role, use the `assign` method:

```php
$team->member($user)->assign('admin');
```

### Removing Members

To remove a member, use the `remove` method. Their groups and [ability rules](abilities.md) in the team are removed too:

```php
$team->remove($user);
```

The `remove` method also accepts an array, collection, or iterable:

```php
$team->remove($users);
```

Batch additions and removals run in one transaction. Every user is validated before changes begin; if a write or listener fails, the whole batch is rolled back. Membership observers run for each user, and completion events are dispatched for each changed member after commit. Empty batches do nothing.

An unknown role throws a `ModelNotFoundException`. Adding a user who is already a member, or changing or removing a user who isn't, throws a `RuntimeException`.

> [!NOTE]
> The team owner isn't a member, so the owner can't be added, reassigned, or removed.

## Retrieving Teams and Members

The `owner` and `members` relationships return the team's owner and members. The owner is never part of `members`:

```php
$team->owner;
$team->members;
```

On the user, `teams` returns the teams the user owns, and `memberships` returns the teams the user has joined:

```php
$user->teams;
$user->memberships;
```

Each team returned by `memberships` has a `membership` attribute with the member's `role_id` and timestamps.

To query both owned and joined teams, use `accessible`. It returns an Eloquent builder, so you can filter, sort, and paginate the results:

```php
$teams = $user->accessible()->orderBy('name')->paginate(15);
```

The query runs in the database and returns each team once. Pending invitations and global groups don't add teams to this list.

To determine if a user owns a team or belongs to it, use the `owns` and `in` methods. The `in` method returns `true` for the owner as well:

```php
if ($user->owns($team)) {
    // ...
}

if ($user->in($team)) {
    // ...
}
```

## Transferring Ownership

To make another user the owner of a team, use the `transfer` method. If the user was a member, their membership is replaced by ownership:

```php
$team->transfer($user);
```

By default, the previous owner leaves the team along with their groups and [ability rules](abilities.md). To keep them as a member, pass a role:

```php
$team->transfer($user, 'admin');
```

## Deleting Teams

To delete a team together with its members, roles, groups, permissions, and invitations, use the `purge` method:

```php
$team->purge();
```

> [!WARNING]
> Calling `delete` on a team that still has members fails. Use `purge` instead.
