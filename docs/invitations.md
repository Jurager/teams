---
title: Invitations
weight: 100
---

# Invitations

If enabled, invitations let team owners/admin flows add users by email.

## Send Invitation

```php
$team->inviteUser('member@example.com', 'editor');
```

This creates an invitation record and sends `Jurager\Teams\Mail\Invitation`.

## Accept Invitation

```php
$team->inviteAccept($invitationId);
```

The method finds the invitation user by email, adds them to the team with invitation role, and deletes the invitation.

> [!NOTE]
> Invitation links are generated with `URL::signedRoute(...)` in `Jurager\Teams\Mail\Invitation`.

## Route Registration

When both flags are enabled:

- `teams.invitations.enabled = true`
- `teams.invitations.routes.register = true`

service provider registers route:

- name: `teams.invitations.accept`
- url: from `teams.invitations.routes.url`
- middleware: from `teams.invitations.routes.middleware`

> [!NOTE]
> Route is loaded through package route group. If you use custom route prefix settings, verify final URL with `php artisan route:list`.

> [!WARNING]
> Package route file has optional `signed` middleware commented out. If you require strict signed-link validation, enable it in your route/controller implementation.
