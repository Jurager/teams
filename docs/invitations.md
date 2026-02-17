---
title: Invitations
weight: 100
---

# Invitations

If enabled (`teams.invitations.enabled = true`), invitations let you add users to a team by email before they have an account or without searching for their user ID.

## Send Invitation

```php
$team->inviteUser('member@example.com', 'editor');
```

This creates an `Invitation` record and dispatches `Jurager\Teams\Mail\Invitation` to the provided email.

> [!WARNING]
> `inviteUser()` throws `RuntimeException` if the email address already belongs to a current team member, or if the specified role code / ID does not exist in the team.

> [!NOTE]
> The invitation email is sent immediately via `Mail::to()->send()` — it is **not** queued by default. If you want queued delivery, swap the `Invitation` mailable for a queued mailable in a custom implementation.

## Accept Invitation

```php
$team->inviteAccept($invitationId);
```

The method:
1. Finds the invitation record by ID (scoped to the team).
2. Resolves the user by the invitation's `email` field.
3. Calls `$team->addUser($user, $invitation->role_id)`.
4. Deletes the invitation record.

> [!WARNING]
> `inviteAccept()` throws `RuntimeException` if the invitation is not found, if no user with the invitation's email exists in the database, or if `addUser()` itself fails (e.g., user already in the team).

> [!NOTE]
> Invitation links are generated with `URL::signedRoute(...)` in `Jurager\Teams\Mail\Invitation`. The link signature is not validated automatically — see the warning below.

## Route Registration

When both flags are enabled:

- `teams.invitations.enabled = true`
- `teams.invitations.routes.register = true`

the service provider registers:

- **Name**: `teams.invitations.accept`
- **URL**: from `teams.invitations.routes.url`
- **Middleware**: from `teams.invitations.routes.middleware`

> [!NOTE]
> The route is loaded through the package route group. If you use custom prefix settings, verify the final URL with `php artisan route:list`.

> [!WARNING]
> The package route file has the `signed` middleware **commented out**. If you require strict signed-link validation (recommended for production), enable it in your route or controller implementation to prevent invitation link forgery.
