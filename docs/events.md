---
title: Events
weight: 120
---

# Events

## Available Events

The package dispatches the following events. All of them live in the `Jurager\Teams\Events` namespace:

| Event                 | Properties                                                 | Dispatched                                                          |
|-----------------------|------------------------------------------------------------|---------------------------------------------------------------------|
| `TeamCreating`        | `team`                                                     | Before a team is created                                            |
| `TeamCreated`         | `team`                                                     | After a team is created                                             |
| `TeamUpdating`        | `team`                                                     | Before a team is updated                                            |
| `TeamUpdated`         | `team`                                                     | After a team is updated                                             |
| `TeamDeleted`         | `team`                                                     | After a team is deleted                                             |
| `TeamTransferring`    | `team`, `owner`, `previous`                                | Before the team gets a new owner                                    |
| `TeamTransferred`     | `team`, `owner`, `previous`                                | After the team gets a new owner                                     |
| `TeamMemberAdding`    | `team`, `user`                                             | Before a member is added                                            |
| `TeamMemberAdded`     | `team`, `user`                                             | After a member is added                                             |
| `TeamMemberUpdated`   | `team`, `user`                                             | After a member's role is changed                                    |
| `TeamMemberRemoving`  | `team`, `user`                                             | Before a member is removed                                          |
| `TeamMemberRemoved`   | `team`, `user`                                             | After a member is removed                                           |
| `TeamMemberInviting`  | `team`, `email`, `role`                                    | Before an invitation is saved                                       |
| `TeamMemberInvited`   | `team`, `email`, `role`                                    | After an invitation is saved, before the email is sent              |
| `PermissionsUpdated`  | `model`, `attached`, `detached`                            | After `grant`, `revoke`, or `sync` changes permissions              |
| `GroupMembersUpdated` | `group`, `attached`, `detached`                            | After `add` or `remove` changes a group's members                   |
| `AbilityUpdated`      | Record, recipient, permission and state values (see below) | After `allow`, `forbid`, or `forget` changes a rule or its deadline |

`attached` and `detached` contain permission or user IDs.

`AbilityUpdated` contains immutable values describing the change, rather than Eloquent models:

| Properties                       | Meaning                                                             |
|----------------------------------|---------------------------------------------------------------------|
| `abilityId`, `teamId`            | The rule and its team                                               |
| `permissionId`, `permission`     | The permission ID and code                                          |
| `entityType`, `entityId`         | The target record's morph type and key                              |
| `recipientType`, `recipientId`   | The user, role, or group's morph type and key                       |
| `previous`, `forbidden`          | Old and new state: `null` absent, `false` allowed, `true` forbidden |
| `previousExpiresAt`, `expiresAt` | Old and new immutable Carbon deadlines, or `null`                   |

Changing only the deadline also dispatches `AbilityUpdated`. Repeating the same state and deadline does not. The values remain usable by queued listeners after a rule is removed, and describe each individual change when several changes commit together.

Assignment events are dispatched only when the package methods change assignments. Direct relationship queries, bulk operations, and deletion cleanup don't dispatch them.

## Listening for Events

You may register listeners in the `boot` method of your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamMemberAdded;

Event::listen(function (TeamMemberAdded $event) {
    $event->user->notify(new WelcomeToTeam($event->team));
});
```

The "after" events are dispatched once the database transaction is committed, and not at all if it is rolled back, so they are safe for sending emails and notifications.

## Cancelling an Action

The "before" events run inside the operation. To cancel the operation, throw an exception from the listener:

```php
use Jurager\Teams\Events\TeamMemberAdding;

Event::listen(function (TeamMemberAdding $event) {
    if ($event->team->members()->count() >= 10) {
        throw new TeamIsFullException;
    }
});
```

Member changes, ownership transfers, invitation writes, and `purge` throw a `RuntimeException` if an Eloquent listener cancels a required write. The transaction is rolled back and completion events aren't dispatched.
