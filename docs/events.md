---
title: Events
weight: 120
---

# Events

The package dispatches lifecycle events for teams and membership changes. Use listeners or subscribers to add audit logging, notifications, or external sync logic.

## Team Events

| Event           | Payload properties | Dispatched when             |
|-----------------|--------------------|-----------------------------|
| `TeamCreating`  | `owner`            | Before team is saved        |
| `TeamCreated`   | `team`             | After team is saved         |
| `TeamUpdating`  | `team`             | Before team update is saved |
| `TeamUpdated`   | `team`             | After team update is saved  |
| `TeamDeleted`   | `team`             | After team is deleted       |

> [!NOTE]
> `TeamCreating` carries `owner` (the user creating the team), not `team`, because the team record does not exist yet at that point.

## Member Events

| Event                | Payload properties        | Dispatched when                   |
|----------------------|---------------------------|-----------------------------------|
| `TeamMemberAdding`   | `team`, `user`            | Before user is attached to team   |
| `TeamMemberAdded`    | `team` (fresh), `user`    | After user is attached to team    |
| `TeamMemberRemoving` | `team`, `user`            | Before user is detached from team |
| `TeamMemberRemoved`  | `team` (fresh), `user`    | After user is detached from team  |
| `TeamMemberUpdated`  | `team` (fresh), `user` (fresh) | After user role is updated   |
| `TeamMemberInviting` | `team`, `email`, `role`   | Before invitation record is created |
| `TeamMemberInvited`  | `team`, `email`, `role`   | After invitation email is sent    |

> [!NOTE]
> Events with `team (fresh)` reload the team from the database after the mutation, so listeners always receive up-to-date relationship data.

> [!WARNING]
> `TeamMemberAdding` and `TeamMemberRemoving` fire **before** the database mutation. If a listener throws an exception, the user will not be added/removed. Do not rely on these events for post-mutation logic — use `TeamMemberAdded` / `TeamMemberRemoved` instead.

## Listening to Events

```php
// app/Providers/AppServiceProvider.php
use Jurager\Teams\Events\TeamMemberAdded;

Event::listen(TeamMemberAdded::class, function (TeamMemberAdded $event) {
    // $event->team, $event->user
    \Log::info("User {$event->user->id} joined team {$event->team->id}");
});
```
