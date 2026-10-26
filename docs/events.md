---
title: Events
weight: 120
---

# Events

## Available Events

The package dispatches the following events. All of them live in the `Jurager\Teams\Events` namespace:

| Event                | Properties              | Dispatched                                             |
|----------------------|-------------------------|--------------------------------------------------------|
| `TeamCreating`       | `team`                  | Before a team is created                               |
| `TeamCreated`        | `team`                  | After a team is created                                |
| `TeamUpdating`       | `team`                  | Before a team is updated                               |
| `TeamUpdated`        | `team`                  | After a team is updated                                |
| `TeamDeleted`        | `team`                  | After a team is deleted                                |
| `TeamMemberAdding`   | `team`, `user`          | Before a member is added                               |
| `TeamMemberAdded`    | `team`, `user`          | After a member is added                                |
| `TeamMemberUpdated`  | `team`, `user`          | After a member's role is changed                       |
| `TeamMemberRemoving` | `team`, `user`          | Before a member is removed                             |
| `TeamMemberRemoved`  | `team`, `user`          | After a member is removed                              |
| `TeamMemberInviting` | `team`, `email`, `role` | Before an invitation is saved                          |
| `TeamMemberInvited`  | `team`, `email`, `role` | After an invitation is saved, before the email is sent |

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
