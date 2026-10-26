---
title: Teams
weight: 40
---

# Teams

- [Creating Teams](#creating-teams)
- [Managing Members](#managing-members)
- [Inspecting Members](#inspecting-members)
- [Authorization](#authorization)
- [Deleting Teams](#deleting-teams)

## Creating Teams

A team is created through its owner. Add at least one [role](roles-permissions.md) before adding members:

```php
$team = $user->ownedTeams()->create(['name' => 'Acme']);

$team->addRole('editor', ['articles.view', 'articles.edit']);
```

## Managing Members

Every member has one role in a team. Pass the role code or ID:

```php
$team->addUser($user, 'editor');
$team->updateUser($user, 'admin');
$team->deleteUser($user);
```

Removing a member also removes their groups and personal [ability rules](abilities.md) in this team.

A missing role throws `ModelNotFoundException`. Adding an existing member, or changing and removing a non-member, throws `RuntimeException`.

> [!NOTE]
> The owner is not a member, so these methods cannot be used on the owner.

## Inspecting Members

```php
$team->owner;                    // the owner
$team->users;                    // members, without the owner
$team->allUsers();               // members and the owner

$team->hasUser($user);           // member or owner
$team->hasUserWithEmail($email); // member or owner
$team->userRole($user);          // Role, Owner, or null
$team->userHasPermission($user, 'articles.edit');
```

## Authorization

The team methods do not check who calls them. Protect them with a policy:

```php
class TeamPolicy
{
    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'members.add');
    }
}
```

Register it in the `boot` method of your `AppServiceProvider`, then authorize before calling the team method:

```php
Gate::policy(config('teams.models.team'), TeamPolicy::class);
```

```php
Gate::authorize('addMember', $team);

$team->addUser($user, 'editor');
```

## Deleting Teams

```php
$team->purge();
```

Purge removes all members and deletes the team with its roles, groups, permissions, and invitations.

> [!WARNING]
> `$team->delete()` fails while the team has members. Use `purge()`.
