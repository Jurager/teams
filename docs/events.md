---
title: Events
weight: 120
---

# Events

- [Available Events](#available-events)
- [Listening for Events](#listening-for-events)
- [Cancelling an Action](#cancelling-an-action)

## Available Events

All events live in the `Jurager\Teams\Events` namespace.

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

Register listeners in the `boot` method of your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamMemberAdded;

Event::listen(function (TeamMemberAdded $event) {
    $event->user->notify(new WelcomeToTeam($event->team));
});
```

The "after" events are dispatched once the database transaction commits, and not at all if it rolls back. They are safe for emails and notifications.

## Cancelling an Action

The "before" events run inside the operation. Throw an exception to cancel it:

```php
use Jurager\Teams\Events\TeamMemberAdding;

Event::listen(function (TeamMemberAdding $event) {
    if ($event->team->users()->count() >= 10) {
        throw new TeamIsFullException;
    }
});
```
